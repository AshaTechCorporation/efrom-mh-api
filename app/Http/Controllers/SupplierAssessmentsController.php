<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\SupplierAssessments;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierAssessmentsController extends Controller
{
    private const MENU_KEY = 'supplier_assessments';

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
                    ->orWhere('menus.path', 'like', '%supplier_assessment%')
                    ->orWhere('menus.path', 'like', '%supplier_assessments%');
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

    // =========== getList ===========
    public function getList()
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $query = SupplierAssessments::query()->orderBy('id', 'desc');
        $this->applyViewScope($query, $ctx);
        $Item = $query->get();

        if (!empty($Item)) {
            for ($i = 0; $i < count($Item); $i++) {
                $Item[$i]['No'] = $i + 1;
                $Item[$i]['permissions'] = $this->recordPermissionPayload($ctx, $Item[$i]);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'items' => $Item,
        ]);
    }

    // =========== getPage (DataTables style) ===========
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
        $page    = $start / $length + 1;

        $Recommendation = $request->recommendation;
        $ApprovedList   = $request->approved_to_supplier_list;

        $col = [
            'id',
            'items_supplied',
            'company_name',
            'attachments',
            'experience_score',
            'staff_score',
            'product_compliance_score',
            'total_score',
            'recommendation',
            'approved_to_supplier_list',
            'assessed_by',
            'assessed_by_date',
            'assessed_by_status',
            'approved_by',
            'approved_by_date',
            'approved_by_status',
            'acknowledged_by',
            'acknowledged_by_date',
            'acknowledged_by_status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        $orderby = [
            '',
            'items_supplied',
            'company_name',
            'total_score',
            'recommendation',
            'approved_to_supplier_list',
            'assessed_date',
            'approved_date',
            'create_by',
        ];

        $D = SupplierAssessments::select($col);
        $this->applyViewScope($D, $ctx);

        if (!empty($Recommendation)) {
            $D->where('recommendation', $Recommendation);
        }

        if ($ApprovedList !== null && $ApprovedList !== '') {
            $D->where('approved_to_supplier_list', $ApprovedList);
        }

        // sort
        if ($orderby[$order[0]['column']] ?? false) {
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

        $d = $D->paginate($length, ['*'], 'page', $page);

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

    // =========== show ===========
    public function show($id)
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $Item = SupplierAssessments::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        if (!$this->canViewRecord($ctx, $Item)) {
            return $this->forbiddenResponse();
        }
        $Item->permissions = $this->recordPermissionPayload($ctx, $Item);

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'item' => $Item,
        ]);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['create'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $loginBy = $request->login_by;

        // validate แบบ snake_case ที่จำเป็น
        if (!isset($request->items_supplied)) {
            return $this->returnErrorData('กรุณาระบุ items_supplied', 404);
        }
        if (!isset($request->company_name)) {
            return $this->returnErrorData('กรุณาระบุ company_name', 404);
        }
        if (!isset($request->total_score)) {
            return $this->returnErrorData('กรุณาระบุ total_score', 404);
        }
        if (!isset($request->recommendation)) {
            return $this->returnErrorData('กรุณาระบุ recommendation', 404);
        }

        // แปลงวันที่ให้รองรับเวลา และบันทึกเป็น datetime (POST: assessed_by_date ใช้ created_at หลัง save)
        $approved_by_date     = $request->approved_by_date;
        $acknowledged_by_date = $request->acknowledged_by_date;

        $approved_by_date = $this->normalizeDateTimeInput($approved_by_date);
        $acknowledged_by_date = $this->normalizeDateTimeInput($acknowledged_by_date);

        DB::beginTransaction();

        try {

            $Item = new SupplierAssessments();
            // Assessment Details
            $Item->items_supplied = $request->items_supplied ?? null;
            $Item->company_name   = $request->company_name ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile            = !empty($request->info_company_profile) ? 1 : 0;
            $Item->info_project_reference          = !empty($request->info_project_reference) ? 1 : 0;
            $Item->info_site_visit                 = !empty($request->info_site_visit) ? 1 : 0;
            $Item->info_previous_assessment_record = !empty($request->info_previous_assessment_record) ? 1 : 0;
            $Item->info_previous_evaluation_record = !empty($request->info_previous_evaluation_record) ? 1 : 0;
            $Item->info_iso_certificates           = !empty($request->info_iso_certificates) ? 1 : 0;

            // Assessment Areas score
            $Item->experience_score         = $request->experience_score ?? 0;
            $Item->staff_score              = $request->staff_score ?? 0;
            $Item->product_compliance_score = $request->product_compliance_score ?? 0;
            $Item->total_score              = $request->total_score ?? 0;

            // References
            $Item->reference_a_name    = $request->reference_a_name ?? null;
            $Item->reference_a_opinion = $request->reference_a_opinion ?? null;
            $Item->reference_b_name    = $request->reference_b_name ?? null;
            $Item->reference_b_opinion = $request->reference_b_opinion ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null;
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Assessed & Approval workflow (POST: assessed_by_date = created_at หลัง save)
            $Item->assessed_by  = $request->assessed_by ?? null;

            $Item->approved_to_supplier_list = !empty($request->approved_to_supplier_list) ? 1 : 0;
            $Item->remark                    = $request->remark ?? null;

            $Item->approved_by   = $request->approved_by ?? null;
            $Item->approved_by_date = $approved_by_date;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $acknowledged_by_date;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $Item->attachments = $this->encodeAttachments($normalizedAttachments);

            $Item->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $Item->created_by = (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? ''));

            $Item->save();

            $Item->assessed_by_date = $Item->created_at;
            $Item->timestamps = false;
            $Item->save();
            $Item->timestamps = true;

            $Item->attachments = $normalizedAttachments;

            DB::commit();
            $Item->permissions = $this->recordPermissionPayload($ctx, $Item);
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $Item,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== update ===========
    public function update(Request $request, $id)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $loginBy = $request->login_by;

        // validate เหมือน store
        if (!isset($request->items_supplied)) {
            return $this->returnErrorData('กรุณาระบุ items_supplied', 404);
        }
        if (!isset($request->company_name)) {
            return $this->returnErrorData('กรุณาระบุ company_name', 404);
        }
        if (!isset($request->total_score)) {
            return $this->returnErrorData('กรุณาระบุ total_score', 404);
        }
        if (!isset($request->recommendation)) {
            return $this->returnErrorData('กรุณาระบุ recommendation', 404);
        }

        // แปลงวันที่ให้รองรับเวลา และบันทึกเป็น datetime
        $assessed_by_date     = $request->assessed_by_date;
        $approved_by_date     = $request->approved_by_date;
        $acknowledged_by_date = $request->acknowledged_by_date;

        $assessed_by_date = $this->normalizeDateTimeInput($assessed_by_date);
        $approved_by_date = $this->normalizeDateTimeInput($approved_by_date);
        $acknowledged_by_date = $this->normalizeDateTimeInput($acknowledged_by_date);

        DB::beginTransaction();

        try {

            $Item = SupplierAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }
            if (!$this->canEditRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            // Assessment Details
            $Item->items_supplied = $request->items_supplied ?? null;
            $Item->company_name   = $request->company_name ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile            = !empty($request->info_company_profile) ? 1 : 0;
            $Item->info_project_reference          = !empty($request->info_project_reference) ? 1 : 0;
            $Item->info_site_visit                 = !empty($request->info_site_visit) ? 1 : 0;
            $Item->info_previous_assessment_record = !empty($request->info_previous_assessment_record) ? 1 : 0;
            $Item->info_previous_evaluation_record = !empty($request->info_previous_evaluation_record) ? 1 : 0;
            $Item->info_iso_certificates           = !empty($request->info_iso_certificates) ? 1 : 0;

            // Assessment Areas score
            $Item->experience_score         = $request->experience_score ?? 0;
            $Item->staff_score              = $request->staff_score ?? 0;
            $Item->product_compliance_score = $request->product_compliance_score ?? 0;
            $Item->total_score              = $request->total_score ?? 0;

            // References
            $Item->reference_a_name    = $request->reference_a_name ?? null;
            $Item->reference_a_opinion = $request->reference_a_opinion ?? null;
            $Item->reference_b_name    = $request->reference_b_name ?? null;
            $Item->reference_b_opinion = $request->reference_b_opinion ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null;
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Assessed & Approval workflow
            $Item->assessed_by  = $request->assessed_by ?? null;
            $Item->assessed_by_date = $assessed_by_date;

            $Item->approved_to_supplier_list = !empty($request->approved_to_supplier_list) ? 1 : 0;
            $Item->remark                    = $request->remark ?? null;

            $Item->approved_by   = $request->approved_by ?? null;
            $Item->approved_by_date = $approved_by_date;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $acknowledged_by_date;

            if ($request->has('attachments')) {
                $attachments = $request->input('attachments');
                $normalizedAttachments = $this->normalizeAttachments($attachments);
                $Item->attachments = $this->encodeAttachments($normalizedAttachments);
            }

            $Item->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';

            $Item->save();
            if (isset($normalizedAttachments)) {
                $Item->attachments = $normalizedAttachments;
            }

            DB::commit();
            $Item->permissions = $this->recordPermissionPayload($ctx, $Item);
            return $this->returnSuccess('อัปเดตข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $Item,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== destroy ===========
    public function destroy($id, Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $loginBy = $request->login_by;

        if (!isset($id)) {
            return $this->returnErrorData('ไม่พบข้อมูล id', 404);
        }

        DB::beginTransaction();

        try {

            $Item = SupplierAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }
            if (!$this->canDeleteRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            $Item->delete();

            // log
            $userId      = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $type        = 'ลบ Supplier Assessment';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' #' . $Item->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }
}
