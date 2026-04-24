<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\SupplierEvaluation;
use App\Models\SupplierEvaluationItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierEvaluationController extends Controller
{
    private const MENU_KEY = 'supplier_evaluation';

    private function unauthorizedResponse()
    {
        return response()->json([
            'code' => '401',
            'status' => false,
            'message' => 'Unauthorized',
            'data' => [],
        ], 401);
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'code' => '403',
            'status' => false,
            'message' => 'Forbidden',
            'data' => [],
        ], 403);
    }

    private function resolveLoginUserId(Request $request): ?int
    {
        if (isset($request->login_id) && is_numeric($request->login_id)) {
            return (int) $request->login_id;
        }

        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            if (isset($loginBy->id) && is_numeric($loginBy->id)) {
                return (int) $loginBy->id;
            }
            if (isset($loginBy->user_id) && is_numeric($loginBy->user_id)) {
                return (int) $loginBy->user_id;
            }
        }

        if (is_array($loginBy)) {
            if (isset($loginBy['id']) && is_numeric($loginBy['id'])) {
                return (int) $loginBy['id'];
            }
            if (isset($loginBy['user_id']) && is_numeric($loginBy['user_id'])) {
                return (int) $loginBy['user_id'];
            }
        }

        return null;
    }

    private function resolvePermissionId(Request $request, ?int $userId): ?int
    {
        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy) && isset($loginBy->permission_id) && is_numeric($loginBy->permission_id)) {
            return (int) $loginBy->permission_id;
        }
        if (is_array($loginBy) && isset($loginBy['permission_id']) && is_numeric($loginBy['permission_id'])) {
            return (int) $loginBy['permission_id'];
        }

        if ($userId !== null) {
            $permissionId = User::where('id', $userId)->value('permission_id');
            return is_numeric($permissionId) ? (int) $permissionId : null;
        }

        return null;
    }

    private function permissionContext(Request $request): array
    {
        $userId = $this->resolveLoginUserId($request);
        if ($userId === null) {
            return ['authorized' => false, 'status' => 401];
        }

        $permissionId = $this->resolvePermissionId($request, $userId);
        if ($permissionId === null) {
            return ['authorized' => false, 'status' => 401];
        }

        $row = MenuPermission::query()
            ->join('menus', 'menus.id', '=', 'menu_permissions.menu_id')
            ->where('menu_permissions.permission_id', $permissionId)
            ->whereNull('menu_permissions.deleted_at')
            ->whereNull('menus.deleted_at')
            ->where(function ($q) {
                $q->where('menus.key', self::MENU_KEY)
                    ->orWhere('menus.path', self::MENU_KEY)
                    ->orWhere('menus.path', '/' . self::MENU_KEY)
                    ->orWhere('menus.path', 'like', '%supplier_evaluation%')
                    ->orWhere('menus.path', 'like', '%supplier-evaluation%');
            })
            ->select('menu_permissions.*')
            ->first();

        $create = (int) ($row->create ?? ($row->save ?? 0));
        $viewOwn = (int) ($row->view_own ?? 0);
        $viewAll = (int) ($row->view_all ?? ($row->view ?? 0));
        $editOwn = (int) ($row->edit_own ?? 0);
        $editAll = (int) ($row->edit_all ?? ($row->edit ?? 0));
        $deleteOwn = (int) ($row->delete_own ?? 0);
        $deleteAll = (int) ($row->delete_all ?? ($row->delete ?? 0));

        $actorKeys = $this->resolveActorKeys($request, $userId);

        return [
            'authorized' => true,
            'status' => 200,
            'user_id' => $userId,
            'actor_key' => $actorKeys[0] ?? (string) $userId,
            'actor_keys' => $actorKeys,
            'create' => $create,
            'view_own' => $viewOwn,
            'view_all' => $viewAll,
            'edit_own' => $editOwn,
            'edit_all' => $editAll,
            'delete_own' => $deleteOwn,
            'delete_all' => $deleteAll,
        ];
    }

    private function resolveActorKeys(Request $request, ?int $userId): array
    {
        $keys = [];

        if ($userId !== null) {
            $keys[] = (string) $userId;
            $user = User::find($userId);
            if ($user) {
                if (!empty($user->code)) {
                    $keys[] = (string) $user->code;
                }
                if (!empty($user->username)) {
                    $keys[] = (string) $user->username;
                }
            }
        }

        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            foreach (['employee_code', 'id', 'user_id', 'username'] as $field) {
                if (isset($loginBy->{$field}) && $loginBy->{$field} !== null && $loginBy->{$field} !== '') {
                    $keys[] = (string) $loginBy->{$field};
                }
            }
        }
        if (is_array($loginBy)) {
            foreach (['employee_code', 'id', 'user_id', 'username'] as $field) {
                if (isset($loginBy[$field]) && $loginBy[$field] !== null && $loginBy[$field] !== '') {
                    $keys[] = (string) $loginBy[$field];
                }
            }
        }

        $keys = array_values(array_unique(array_filter($keys, static function ($v) {
            return $v !== '';
        })));

        return $keys;
    }

    private function ownerKeyFromRecord($record): ?string
    {
        if (isset($record->created_by) && $record->created_by !== null && $record->created_by !== '') {
            return (string) $record->created_by;
        }

        $legacy = $record->create_by ?? null;
        if ($legacy !== null && $legacy !== '') {
            return (string) $legacy;
        }

        return null;
    }

    private function ownerMatches(array $ctx, $record): bool
    {
        $ownerKey = $this->ownerKeyFromRecord($record);
        if ($ownerKey === null) {
            return false;
        }

        return in_array($ownerKey, $ctx['actor_keys'] ?? [], true);
    }

    private function canViewRecord(array $ctx, $record): bool
    {
        if (($ctx['view_all'] ?? 0) === 1) {
            return true;
        }
        if (($ctx['view_own'] ?? 0) !== 1) {
            return false;
        }
        return $this->ownerMatches($ctx, $record);
    }

    private function canEditRecord(array $ctx, $record): bool
    {
        if (($ctx['edit_all'] ?? 0) === 1) {
            return true;
        }
        if (($ctx['edit_own'] ?? 0) !== 1) {
            return false;
        }
        return $this->ownerMatches($ctx, $record);
    }

    private function canDeleteRecord(array $ctx, $record): bool
    {
        if (($ctx['delete_all'] ?? 0) === 1) {
            return true;
        }
        if (($ctx['delete_own'] ?? 0) !== 1) {
            return false;
        }
        return $this->ownerMatches($ctx, $record);
    }

    private function applyViewScope($query, array $ctx): void
    {
        if (($ctx['view_all'] ?? 0) === 1) {
            return;
        }

        if (($ctx['view_own'] ?? 0) === 1) {
            $keys = $ctx['actor_keys'] ?? [];
            if (empty($keys)) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->where(function ($q) use ($keys) {
                $q->whereIn('created_by', $keys)
                    ->orWhereIn('create_by', $keys);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function modulePermissionPayload(array $ctx): array
    {
        return [
            'create' => (bool) ($ctx['create'] ?? 0),
            'view_own' => (bool) ($ctx['view_own'] ?? 0),
            'view_all' => (bool) ($ctx['view_all'] ?? 0),
            'edit_own' => (bool) ($ctx['edit_own'] ?? 0),
            'edit_all' => (bool) ($ctx['edit_all'] ?? 0),
            'delete_own' => (bool) ($ctx['delete_own'] ?? 0),
            'delete_all' => (bool) ($ctx['delete_all'] ?? 0),
        ];
    }

    private function recordPermissionPayload(array $ctx, $record): array
    {
        return [
            'can_view' => $this->canViewRecord($ctx, $record),
            'can_edit' => $this->canEditRecord($ctx, $record),
            'can_delete' => $this->canDeleteRecord($ctx, $record),
        ];
    }

    private function normalizeAttachments($attachments)
    {
        if (is_array($attachments)) {
            return $attachments;
        }

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $trimmed = trim($attachments);
            if ($trimmed !== '') {
                return [$trimmed];
            }
        }

        return [];
    }

    private function attachmentsToJson($attachments)
    {
        $normalized = $this->normalizeAttachments($attachments);
        return $this->encodeAttachments($normalized);
    }

    private function encodeAttachments($normalized)
    {
        if (empty($normalized)) {
            return null;
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    // -----------------------------------------
    // GET LIST (ไม่มี paginate)
    // -----------------------------------------
    public function getList(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $query = SupplierEvaluation::orderBy('id', 'desc');
        $this->applyViewScope($query, $ctx);
        $Items = $query->get();
        foreach ($Items as $idx => $item) {
            $Items[$idx]->permissions = $this->recordPermissionPayload($ctx, $item);
        }

        return $this->returnSuccess('Success', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'items' => $Items,
        ]);
    }

    // -----------------------------------------
    // GET PAGE (มี paginate)
    // -----------------------------------------
    public function getPage(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $columns = $request->columns;
        $length  = $request->length;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start;
        $page    = $start / ($length ?: 10) + 1;

        // filter เพิ่มเติมถ้ามี เช่น decision
        $Decision = $request->decision;

        $col = [
            'id',
            'supplier_name',
            'project_name',
            'project_no',
            'department_value_duration',
            'average_rating',
            'decision',
            'attachments',
            'evaluated_by',
            'evaluated_by_date',
            'evaluated_by_status',
            'acknowledged_by',
            'acknowledged_by_date',
            'acknowledged_by_status',
            'approved_by',
            'approved_by_date',
            'approved_by_status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        $orderby = [
            '',
            'supplier_name',
            'project_name',
            'project_no',
            'department_value_duration',
            'average_rating',
            'decision',
            'create_by',
            'created_at',
        ];

        $D = SupplierEvaluation::select($col);
        $this->applyViewScope($D, $ctx);

        // filter decision (ถ้ามี)
        if (!empty($Decision)) {
            $D->where('decision', $Decision);
        }

        // sort (กันเคส column = 0 ไม่ให้สั่ง orderBy ค่าว่าง)
        if (!empty($order) && ($orderby[$order[0]['column']] ?? false)) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        }

        // search
        if (!empty($search['value'])) {
            $D->where(function ($query) use ($search, $col) {
                foreach ($col as $c) {
                    $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        if (!$length || (int)$length <= 0) {
            $length = 10;
        }

        $d = $D->paginate($length, ['*'], 'page', $page);

        // เติมเลขลำดับ No เหมือนที่คุณใช้ตลอด
        if ($d->isNotEmpty()) {
            $No = (($page - 1) * $length);
            for ($i = 0; $i < count($d); $i++) {
                $No        = $No + 1;
                $d[$i]->No = $No;
                $d[$i]->permissions = $this->recordPermissionPayload($ctx, $d[$i]);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'items' => $d,
        ]);
    }



    // -----------------------------------------
    // SHOW
    // -----------------------------------------
    public function show($id)
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $Item = SupplierEvaluation::with(['items'])->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบข้อมูล', 404);
        }

        if (!$this->canViewRecord($ctx, $Item)) {
            return $this->forbiddenResponse();
        }

        $Item->permissions = $this->recordPermissionPayload($ctx, $Item);
        return $this->returnSuccess('Success', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'item' => $Item,
        ]);
    }

    // -----------------------------------------
    // STORE (สร้างใหม่)
    // -----------------------------------------
    public function store(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['create'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        DB::beginTransaction();

        try {

            $Item = new SupplierEvaluation();
            $Item->supplier_name                = $request->supplier_name;
            $Item->project_name                 = $request->project_name;
            $Item->project_no                   = $request->project_no;
            $Item->department_value_duration    = $request->department_value_duration;
            $Item->anti_corruption_flag         = $request->anti_corruption_flag;
            $Item->average_rating               = $request->average_rating;
            $Item->decision                     = $request->decision;

            // POST: evaluated_by_date = created_at หลัง save
            $Item->evaluated_by                  = $request->evaluated_by ?? null;
            $Item->evaluated_by_status           = $request->evaluated_by_status ?? null;
            $Item->acknowledged_by              = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date         = $this->normalizeDateTimeInput($request->acknowledged_by_date ?? null);
            $Item->acknowledged_by_status       = $request->acknowledged_by_status ?? null;
            $Item->approved_by              = $request->approved_by ?? null;
            $Item->approved_by_date         = $this->normalizeDateTimeInput($request->approved_by_date ?? null);
            $Item->approved_by_status       = $request->approved_by_status ?? null;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $Item->attachments = $this->encodeAttachments($normalizedAttachments);

            $Item->create_by = $request->login_by->employee_code ?? $request->login_by->id ?? 'admin';
            $Item->created_by = (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? ''));
            $Item->save();

            $Item->evaluated_by_date = $Item->created_at;
            $Item->timestamps = false;
            $Item->save();
            $Item->timestamps = true;

            $Item->attachments = $normalizedAttachments;

            // ----------------------
            // Save items (8 ข้อ)
            // ----------------------
            if (isset($request->items) && is_array($request->items)) {
                foreach ($request->items as $row) {
                    $detail = new SupplierEvaluationItem();
                    $detail->supplier_evaluation_id = $Item->id;
                    $detail->item_name              = $row['item_name'];
                    $detail->rating                 = $row['rating'] ?? 0;
                    $detail->comment                = $row['comment'] ?? null;
                    $detail->create_by              = $request->login_by->employee_code ?? $request->login_by->id ?? 'admin';
                    $detail->save();
                }
            }

            DB::commit();
            $Item->permissions = $this->recordPermissionPayload($ctx, $Item);
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $Item,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    // -----------------------------------------
    // UPDATE
    // -----------------------------------------
    public function update(Request $request, $id)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        DB::beginTransaction();

        try {
            $Item = SupplierEvaluation::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
            if (!$this->canEditRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            $Item->supplier_name                = $request->supplier_name;
            $Item->project_name                 = $request->project_name;
            $Item->project_no                   = $request->project_no;
            $Item->department_value_duration    = $request->department_value_duration;
            $Item->anti_corruption_flag         = $request->anti_corruption_flag;
            $Item->average_rating               = $request->average_rating;
            $Item->decision                     = $request->decision;

            $Item->evaluated_by                  = $request->evaluated_by ?? null;
            $Item->evaluated_by_date             = $this->normalizeDateTimeInput($request->evaluated_by_date ?? null);
            $Item->evaluated_by_status           = $request->evaluated_by_status ?? null;
            $Item->acknowledged_by              = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date         = $this->normalizeDateTimeInput($request->acknowledged_by_date ?? null);
            $Item->acknowledged_by_status       = $request->acknowledged_by_status ?? null;
            $Item->approved_by              = $request->approved_by ?? null;
            $Item->approved_by_date         = $this->normalizeDateTimeInput($request->approved_by_date ?? null);
            $Item->approved_by_status       = $request->approved_by_status ?? null;

            if ($request->has('attachments')) {
                $attachments = $request->input('attachments');
                $normalizedAttachments = $this->normalizeAttachments($attachments);
                $Item->attachments = $this->encodeAttachments($normalizedAttachments);
            }

            $Item->update_by = $request->login_by->employee_code ?? $request->login_by->id ?? 'admin';
            $Item->save();
            if (isset($normalizedAttachments)) {
                $Item->attachments = $normalizedAttachments;
            }

            // ล้างของเก่า
            SupplierEvaluationItem::where('supplier_evaluation_id', $Item->id)->delete();

            // เพิ่มชุดใหม่
            if (isset($request->items)) {
                foreach ($request->items as $row) {
                    $detail = new SupplierEvaluationItem();
                    $detail->supplier_evaluation_id = $Item->id;
                    $detail->item_name              = $row['item_name'];
                    $detail->rating                 = $row['rating'];
                    $detail->comment                = $row['comment'];
                    $detail->create_by              = $request->login_by->employee_code ?? $request->login_by->id ?? 'admin';
                    $detail->save();
                }
            }

            DB::commit();
            $Item->permissions = $this->recordPermissionPayload($ctx, $Item);
            return $this->returnSuccess('อัปเดตข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $Item,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    // -----------------------------------------
    // DELETE
    // -----------------------------------------
    public function destroy($id)
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $Item = SupplierEvaluation::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบข้อมูล', 404);
        }
        if (!$this->canDeleteRecord($ctx, $Item)) {
            return $this->forbiddenResponse();
        }

        $Item->delete();

        return $this->returnSuccess('ลบข้อมูลสำเร็จ');
    }
}
