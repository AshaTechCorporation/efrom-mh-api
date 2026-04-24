<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;


class PurchaseOrderController extends Controller
{
    private const MENU_KEY = 'purchase_order';

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
                    ->orWhere('menus.path', 'like', '%purchase_order%')
                    ->orWhere('menus.path', 'like', '%purchase-orders%')
                    ->orWhere('menus.path', 'like', '%purchase_orders%');
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

        $query = PurchaseOrder::query()->orderBy('id', 'desc');
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
        $length  = $request->length ?? 10;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start ?? 0;
        $page    = $start / $length + 1;

        $col = array(
            'id',
            'po_no',
            'po_date',
            'requisition_date',
            'to',
            'company',
            'from',
            'quotation_no',
            'delivery_date',
            'payment_term',
            'sub_total',
            'vat_value',
            'discount',
            'grand_total',
            'attachments',
            'purchase_request_by',
            'purchase_request_by_date',
            'purchase_request_by_status',
            'verified_by',
            'verified_by_date',
            'verified_by_status',
            'approved_by',
            'approved_by_date',
            'approved_by_status',
            'signed_by',
            'signed_by_date',
            'signed_by_status',
            'acknowledged_by',
            'acknowledged_by_date',
            'acknowledged_by_status',
            'comment_all',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        );

        $orderby = array(
            '',
            'po_no',
            'po_date',
            'requisition_date',
            'to',
            'company',
            'from',
            'quotation_no',
            'delivery_date',
            'create_by',
        );

        $D = PurchaseOrder::select($col);
        $this->applyViewScope($D, $ctx);

        // order by
        if (!empty($order) && ($orderby[$order[0]['column']] ?? false)) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        }

        // search all columns
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

        $Item = PurchaseOrder::with('items')->find($id);

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

        // validate field หลัก ๆ
        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ to', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ company', 404);
        }
        if (!isset($request->from)) {
            return $this->returnErrorData('กรุณาระบุ from', 404);
        }
        if (!isset($request->po_date)) {
            return $this->returnErrorData('กรุณาระบุ po_date', 404);
        }
        if (!isset($request->requisition_date)) {
            return $this->returnErrorData('กรุณาระบุ requisition_date', 404);
        }
        if (empty($request->items) || !is_array($request->items)) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        if (isset($request->currency_code) && !in_array($request->currency_code, ['THB', 'USD'])) {
            return $this->returnErrorData('currency_code ต้องเป็น THB หรือ USD', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new PurchaseOrder();

            // Header
            $Item->to       = $request->to;
            $Item->company  = $request->company;
            $Item->fax      = $request->fax ?? null;
            $Item->from     = $request->from;
            $Item->cc       = $request->cc ?? null;

            // PO Info (ใช้ค่าที่ส่งมา ตรง ๆ)
            $Item->po_no            = $request->po_no ?? null;
            $Item->po_date          = $request->po_date;
            $Item->requisition_date = $this->normalizeDateTimeInput($request->requisition_date);
            $Item->page             = $request->page ?? 1;
            $Item->total_page       = $request->total_page ?? 1;
            $Item->circ             = $request->circ ?? null;

            // General
            $Item->quotation_no     = $request->quotation_no ?? null;
            $Item->quotation_date   = $request->quotation_date;
            $Item->delivery_date    = $request->delivery_date;
            $Item->payment_term     = $request->payment_term ?? null;
            $Item->other_conditions = $request->other_conditions ?? null;

            $Item->vat = isset($request->vat) ? (bool)$request->vat : false;
            $Item->currency_code = $request->currency_code ?? 'THB';

            $Item->sub_total   = $request->sub_total;
            $Item->vat_value   = $request->vat_value;
            $Item->discount    = $request->discount ?? 0;
            $Item->grand_total = $request->grand_total;

            // Approval & Review
            $Item->purchase_request_by   = $request->purchase_request_by ?? null;
            $Item->purchase_request_by_date = $this->normalizeDateTimeInput($request->purchase_request_by_date ?? null);
            $Item->purchase_request_by_status = $request->purchase_request_by_status ?? null;
            $Item->verified_by           = $request->verified_by ?? null;
            $Item->verified_by_date = $this->normalizeDateTimeInput($request->verified_by_date ?? null);
            $Item->verified_by_status = $request->verified_by_status ?? null;
            $Item->approved_by           = $request->approved_by ?? null;
            $Item->approved_by_date = $this->normalizeDateTimeInput($request->approved_by_date ?? null);
            $Item->approved_by_status = $request->approved_by_status ?? null;

            // Checklist
            $Item->delivery_on_time          = $request->delivery_on_time ?? null;
            $Item->meet_quality_requirement  = $request->meet_quality_requirement ?? null;
            $Item->meet_equipment_guidelines = $request->meet_equipment_guidelines ?? null;

            // Comments & Signatures
            $Item->comments          = $request->comments ?? null;
            $Item->comment_all       = $request->comment_all ?? null;
            $Item->signed_by         = $request->signed_by ?? null;
            $Item->signed_by_date = $this->normalizeDateTimeInput($request->signed_by_date ?? null);
            $Item->signed_by_status = $request->signed_by_status ?? null;
            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $this->normalizeDateTimeInput($request->acknowledged_by_date ?? null);
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? null;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $Item->attachments = $this->encodeAttachments($normalizedAttachments);

            $Item->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $Item->created_by = (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? ''));
            $Item->save();
            $Item->attachments = $normalizedAttachments;

            // Items
            foreach ($request->items as $row) {
                if (empty($row['item']) && empty($row['description'])) {
                    continue;
                }

                $qty   = isset($row['quantity']) ? (int)$row['quantity'] : 0;
                $price = isset($row['unit_price']) ? (float)$row['unit_price'] : 0;
                $amt   = isset($row['amount']) ? (float)$row['amount'] : $qty * $price;

                $detail                    = new PurchaseOrderItem();
                $detail->purchase_order_id = $Item->id;
                $detail->item              = $row['item'] ?? '';
                $detail->description       = $row['description'] ?? null;
                $detail->quantity          = $qty;
                $detail->unit_price        = $price;
                $detail->amount            = $amt;
                $detail->save();
            }

            DB::commit();
            $fresh = $Item->load('items');
            $fresh->permissions = $this->recordPermissionPayload($ctx, $fresh);
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $fresh,
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

        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ to', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ company', 404);
        }
        if (!isset($request->from)) {
            return $this->returnErrorData('กรุณาระบุ from', 404);
        }
        if (!isset($request->po_date)) {
            return $this->returnErrorData('กรุณาระบุ po_date', 404);
        }
        if (!isset($request->requisition_date)) {
            return $this->returnErrorData('กรุณาระบุ requisition_date', 404);
        }
        if (empty($request->items) || !is_array($request->items)) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }
        if (isset($request->currency_code) && !in_array($request->currency_code, ['THB', 'USD'])) {
            return $this->returnErrorData('currency_code ต้องเป็น THB หรือ USD', 404);
        }

        DB::beginTransaction();

        try {

            $Item = PurchaseOrder::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }
            if (!$this->canEditRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }


            // Header
            $Item->to       = $request->to;
            $Item->company  = $request->company;
            $Item->fax      = $request->fax ?? null;
            $Item->from     = $request->from;
            $Item->cc       = $request->cc ?? null;

            // PO Info
            $Item->po_no            = $request->po_no ?? null;
            $Item->po_date          = $request->po_date;
            $Item->requisition_date = $this->normalizeDateTimeInput($request->requisition_date);
            $Item->page             = $request->page ?? 1;
            $Item->total_page       = $request->total_page ?? 1;
            $Item->circ             = $request->circ ?? null;

            // General
            $Item->quotation_no     = $request->quotation_no ?? null;
            $Item->quotation_date   = $request->quotation_date;
            $Item->delivery_date    = $request->delivery_date;
            $Item->payment_term     = $request->payment_term ?? null;
            $Item->other_conditions = $request->other_conditions ?? null;

            $Item->vat = $request->boolean('vat');
            $Item->currency_code = $request->input('currency_code', 'THB');

            $Item->sub_total   = $request->sub_total;
            $Item->vat_value   = $request->vat_value;
            $Item->discount    = $request->discount ?? 0;
            $Item->grand_total = $request->grand_total;

             // Approval & Review
            $Item->purchase_request_by   = $request->purchase_request_by ?? null;
            $Item->purchase_request_by_date = $this->normalizeDateTimeInput($request->purchase_request_by_date ?? null);
            $Item->purchase_request_by_status = $request->purchase_request_by_status ?? null;
            $Item->verified_by           = $request->verified_by ?? null;
            $Item->verified_by_date = $this->normalizeDateTimeInput($request->verified_by_date ?? null);
            $Item->verified_by_status = $request->verified_by_status ?? null;
            $Item->approved_by           = $request->approved_by ?? null;
            $Item->approved_by_date = $this->normalizeDateTimeInput($request->approved_by_date ?? null);
            $Item->approved_by_status = $request->approved_by_status ?? null;

            // Checklist
            $Item->delivery_on_time          = $request->delivery_on_time ?? null;
            $Item->meet_quality_requirement  = $request->meet_quality_requirement ?? null;
            $Item->meet_equipment_guidelines = $request->meet_equipment_guidelines ?? null;

            // Comments & Signatures
            $Item->comments          = $request->comments ?? null;
            $Item->comment_all       = $request->comment_all ?? null;
            $Item->signed_by         = $request->signed_by ?? null;
            $Item->signed_by_date = $this->normalizeDateTimeInput($request->signed_by_date ?? null);
            $Item->signed_by_status = $request->signed_by_status ?? null;
            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $this->normalizeDateTimeInput($request->acknowledged_by_date ?? null);
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? null;

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

            // ลบ items เดิมแล้วสร้างใหม่
            PurchaseOrderItem::where('purchase_order_id', $Item->id)->delete();

            foreach ($request->items as $row) {
                if (empty($row['item']) && empty($row['description'])) {
                    continue;
                }

                $qty   = isset($row['quantity']) ? (int)$row['quantity'] : 0;
                $price = isset($row['unit_price']) ? (float)$row['unit_price'] : 0;
                $amt   = isset($row['amount']) ? (float)$row['amount'] : $qty * $price;

                $detail                    = new PurchaseOrderItem();
                $detail->purchase_order_id = $Item->id;
                $detail->item              = $row['item'] ?? '';
                $detail->description       = $row['description'] ?? null;
                $detail->quantity          = $qty;
                $detail->unit_price        = $price;
                $detail->amount            = $amt;
                $detail->save();
            }

            DB::commit();
            $fresh = $Item->load('items');
            $fresh->permissions = $this->recordPermissionPayload($ctx, $fresh);
            return $this->returnSuccess('อัปเดตข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $fresh,
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

            $Item = PurchaseOrder::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }
            if (!$this->canDeleteRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            $Item->delete();

            // log
            $userId      = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $type        = 'ลบข้อมูล purchase_order';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' #' . $Item->id;
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }


    public function getNextNumber(): JsonResponse
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['create'] ?? 0) !== 1 && ($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $latestPo = PurchaseOrder::whereNotNull('po_no')
        ->where('po_no', '!=', '')
        ->orderBy('po_no', 'desc')
        ->first();

        $nextNumber = 1;

        if ($latestPo) {
        if (preg_match('/(\d+)$/', $latestPo->po_no, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'next_po_no' => $nextNumber
            ]
        ]);
    }
}
