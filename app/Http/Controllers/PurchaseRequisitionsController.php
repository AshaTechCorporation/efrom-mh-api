<?php
namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\PurchaseRequisitions;
use App\Models\PurchaseRequisitionItems;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurchaseRequisitionsController extends Controller
{
    private const MENU_KEY = 'purchase_requisitions';
    private const WORKFLOW_STEP_CONFIG = [
        'verified_by_is' => [
            'assignee_field' => 'verified_by_is',
            'status_field' => 'verified_by_is_status',
            'date_field' => 'verified_is_date',
        ],
        'verified_by' => [
            'assignee_field' => 'verified_by',
            'status_field' => 'verified_by_status',
            'date_field' => 'verified_date',
        ],
        'approved_by' => [
            'assignee_field' => 'approved_by',
            'status_field' => 'approved_by_status',
            'date_field' => 'approved_date',
        ],
        'acknowledged_by' => [
            'assignee_field' => 'acknowledged_by',
            'status_field' => 'acknowledged_by_status',
            'date_field' => 'acknowledged_date',
        ],
    ];

    private function hasCreatedByColumn(): bool
    {
        static $hasCreatedBy = null;

        if ($hasCreatedBy !== null) {
            return $hasCreatedBy;
        }

        try {
            $hasCreatedBy = Schema::hasColumn('purchase_requisitions', 'created_by');
        } catch (\Throwable $e) {
            $hasCreatedBy = false;
        }

        return $hasCreatedBy;
    }

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
                    ->orWhere('menus.path', 'like', '%' . self::MENU_KEY . '%');
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
                if ($this->hasCreatedByColumn()) {
                    $q->whereIn('created_by', $keys)
                        ->orWhereIn('create_by', $keys);
                    return;
                }

                $q->whereIn('create_by', $keys);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function recordPermissionPayload(array $ctx, $record): array
    {
        return [
            'can_view' => $this->canViewRecord($ctx, $record),
            'can_edit' => $this->canEditRecord($ctx, $record),
            'can_delete' => $this->canDeleteRecord($ctx, $record),
        ];
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

    private function normalizeWorkflowStatusValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === 'approve') {
            return 'approved';
        }

        if ($normalized === 'reject') {
            return 'rejected';
        }

        return $normalized;
    }

    private function isApprovedWorkflowStatus($value): bool
    {
        return $this->normalizeWorkflowStatusValue($value) === 'approved';
    }

    private function isRejectedWorkflowStatus($value): bool
    {
        return $this->normalizeWorkflowStatusValue($value) === 'rejected';
    }

    private function isPendingWorkflowStatus($value): bool
    {
        $normalized = $this->normalizeWorkflowStatusValue($value);
        return $normalized === null || $normalized === 'pending';
    }

    private function hasWorkflowAssignee($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function actorMatchesValue(array $ctx, $value): bool
    {
        if (!$this->hasWorkflowAssignee($value)) {
            return false;
        }

        return in_array((string) $value, $ctx['actor_keys'] ?? [], true);
    }

    private function normalizeComparableDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return date('Y-m-d H:i:s', strtotime((string) $value));
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function normalizeComparableScalar($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (string) ((float) $value);
        }

        return trim((string) $value);
    }

    private function currentWorkflowStep($pr): ?string
    {
        $steps = self::WORKFLOW_STEP_CONFIG;

        foreach ($steps as $step => $config) {
            $statusValue = $pr->{$config['status_field']} ?? null;
            if ($this->isRejectedWorkflowStatus($statusValue)) {
                return null;
            }
        }

        $verifiedByIs = $steps['verified_by_is'];
        $verifiedByIsAssignee = $pr->{$verifiedByIs['assignee_field']} ?? null;
        $verifiedByIsStatus = $pr->{$verifiedByIs['status_field']} ?? null;
        if ($this->hasWorkflowAssignee($verifiedByIsAssignee) && !$this->isApprovedWorkflowStatus($verifiedByIsStatus)) {
            return 'verified_by_is';
        }

        foreach (['verified_by', 'approved_by', 'acknowledged_by'] as $step) {
            $config = $steps[$step];
            $assignee = $pr->{$config['assignee_field']} ?? null;
            $statusValue = $pr->{$config['status_field']} ?? null;
            $stepUsed = $this->hasWorkflowAssignee($assignee) || $statusValue !== null;

            if ($stepUsed && !$this->isApprovedWorkflowStatus($statusValue)) {
                return $step;
            }
        }

        return null;
    }

    private function detectWorkflowTransition(Request $request, $pr): array
    {
        $changes = [];

        foreach (self::WORKFLOW_STEP_CONFIG as $step => $config) {
            $statusField = $config['status_field'];
            $dateField = $config['date_field'];

            $statusChanged = $request->has($statusField)
                && $this->normalizeWorkflowStatusValue($request->input($statusField))
                    !== $this->normalizeWorkflowStatusValue($pr->{$statusField} ?? null);

            $dateChanged = $request->has($dateField)
                && $this->normalizeComparableDateTime($request->input($dateField))
                    !== $this->normalizeComparableDateTime($pr->{$dateField} ?? null);

            $extraChanged = false;
            if ($step === 'acknowledged_by' && $request->has('need_asset_code_registration')) {
                $extraChanged = (int) $request->input('need_asset_code_registration')
                    !== (int) ($pr->need_asset_code_registration ?? 0);
            }

            if ($statusChanged || $dateChanged || $extraChanged) {
                $changes[] = [
                    'step' => $step,
                    'status_changed' => $statusChanged,
                    'date_changed' => $dateChanged,
                    'extra_changed' => $extraChanged,
                ];
            }
        }

        return $changes;
    }

    private function authorizeWorkflowTransition(Request $request, $pr, array $ctx): array
    {
        $changes = $this->detectWorkflowTransition($request, $pr);
        if (count($changes) === 0) {
            return ['has_transition' => false, 'authorized' => false];
        }

        if (count($changes) !== 1) {
            return ['has_transition' => true, 'authorized' => false, 'reason' => 'multiple_steps'];
        }

        $change = $changes[0];
        $expectedStep = $this->currentWorkflowStep($pr);
        if ($expectedStep === null || $change['step'] !== $expectedStep) {
            return ['has_transition' => true, 'authorized' => false, 'reason' => 'not_current_step'];
        }

        $config = self::WORKFLOW_STEP_CONFIG[$expectedStep];
        $assignee = $pr->{$config['assignee_field']} ?? null;
        if (!$this->actorMatchesValue($ctx, $assignee)) {
            return ['has_transition' => true, 'authorized' => false, 'reason' => 'not_assignee'];
        }

        $incomingStatus = $request->has($config['status_field'])
            ? $this->normalizeWorkflowStatusValue($request->input($config['status_field']))
            : $this->normalizeWorkflowStatusValue($pr->{$config['status_field']} ?? null);

        if (!in_array($incomingStatus, ['pending', 'approved', 'rejected'], true)) {
            return ['has_transition' => true, 'authorized' => false, 'reason' => 'invalid_status'];
        }

        return [
            'has_transition' => true,
            'authorized' => true,
            'step' => $expectedStep,
            'config' => $config,
        ];
    }

    private function applyWorkflowOnlyUpdate($pr, Request $request, array $workflowAuth): void
    {
        $config = $workflowAuth['config'];
        $statusField = $config['status_field'];
        $dateField = $config['date_field'];

        if ($request->has($statusField)) {
            $pr->{$statusField} = $request->input($statusField);
        }

        if ($request->has($dateField)) {
            $pr->{$dateField} = $this->normalizeDateTimeInput($request->input($dateField));
        }

        if (($workflowAuth['step'] ?? null) === 'acknowledged_by' && $request->has('need_asset_code_registration')) {
            $pr->need_asset_code_registration = $request->input('need_asset_code_registration');
        }
    }

    // ================= getList =================
    public function getList()
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $query = PurchaseRequisitions::with('items')
            ->orderBy('id', 'desc');
        $this->applyViewScope($query, $ctx);

        $Item = $query
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Item as $i => $v) {
            $Item[$i]['No'] = $i + 1;
            $Item[$i]['permissions'] = $this->recordPermissionPayload($ctx, $v);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'items' => $Item,
        ]);
    }

    // ================= getPage (DataTable) =================
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
        $page    = floor($start / $length) + 1;

        $status  = $request->approved_by_status; // ตัวกรอง optional

        $col = [
            'id',
            'to',
            'date',
            'deadline',
            'attachments',
            'recommended_by',
            'vat',
            'currency_code',
            'received_from',
            'reasons_for_purchase',
            'other_conditions',
            'quotation_attached',
            'requested_by',
            'requested_by_status',
            'requested_date',
            'verified_by_is',
            'approved_by',
            'approved_by_status',
            'approved_date',
            'verified_is_date',
            'verified_by_is_status',
            'verified_by',
            'verified_by_status',
            'verified_date',
            'acknowledged_by',
            'acknowledged_by_status',
            'acknowledged_date',
            'need_asset_code_registration',
            'action_by_admin',
            'action_by_admin_status',
            'action_by_admin_date',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
            'deleted_at',
            'sub_total',
            'vat_value',
            'grand_total'
        ];

        $orderby = [
            '',
            'to',
            'date',
            'deadline',
            'recommended_by',
            'received_from',
            'requested_by',
            'requested_by_status',
            'approved_by',
            'approved_by_status',
            'created_at',
        ];

        $D = PurchaseRequisitions::select($col);
        $this->applyViewScope($D, $ctx);

        if (!empty($status)) {
            $D->where('approved_by_status', $status);
        }

        if (!empty($search['value'])) {
            $keyword = '%' . $search['value'] . '%';
            $D->where(function ($q) use ($keyword, $col) {
                foreach ($col as $c) {
                    $q->orWhere($c, 'like', $keyword);
                }
            });
        }

        if (!empty($order)) {
            $idx = $order[0]['column'];
            $dir = $order[0]['dir'];
            if (isset($orderby[$idx]) && $orderby[$idx] !== '') {
                $D->orderBy($orderby[$idx], $dir);
            }
        } else {
            $D->orderBy('id', 'desc');
        }

        $data = $D->get();

        if ($data->isNotEmpty()) {
            $no = 0;
            foreach ($data as $row) {
                $row->No = ++$no;
                $row->permissions = $this->recordPermissionPayload($ctx, $row);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'permissions' => $this->modulePermissionPayload($ctx),
            'items' => $data,
        ]);
    }

    // ================= show =================
    public function show($id)
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $Item = PurchaseRequisitions::with('items')->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบข้อมูลที่ระบุ', 404);
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

    // ================= store =================
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

        if (empty($request->to))   return $this->returnErrorData('กรุณาระบุ to', 404);
        if (empty($request->date)) return $this->returnErrorData('กรุณาระบุ date', 404);

        $items = $request->items ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        if (isset($request->currency_code) && !in_array($request->currency_code, ['THB', 'USD'])) {
            return $this->returnErrorData('currency_code ต้องเป็น THB หรือ USD', 404);
        }


        DB::beginTransaction();

        try {
            $pr = new PurchaseRequisitions();
            $pr->to                      = $request->to;
            $pr->date                    = $request->date;
            $pr->deadline                = $request->deadline;
            $pr->recommended_by          = $request->recommended_by;
            $pr->received_from           = $request->received_from;
            $pr->reasons_for_purchase    = $request->reasons_for_purchase;
            $pr->other_conditions        = $request->other_conditions;
            $pr->quotation_attached      = $request->quotation_attached;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $pr->attachments = $this->encodeAttachments($normalizedAttachments);

            $pr->requested_by            = $request->requested_by;
            $pr->requested_by_status     = $request->requested_by_status;
            $pr->requested_date          = $this->normalizeDateTimeInput($request->requested_date);

            $pr->verified_by_is          = $request->verified_by_is;
            $pr->verified_by_is_status   = $request->verified_by_is_status;
            $pr->verified_is_date        = $this->normalizeDateTimeInput($request->verified_is_date);

            $pr->verified_by             = $request->verified_by;
            $pr->verified_by_status      = $request->verified_by_status;
            $pr->verified_date           = $this->normalizeDateTimeInput($request->verified_date);

            $pr->approved_by             = $request->approved_by;
            $pr->approved_by_status      = $request->approved_by_status;
            $pr->approved_date           = $this->normalizeDateTimeInput($request->approved_date);

            $pr->acknowledged_by         = $request->acknowledged_by;
            $pr->acknowledged_by_status  = $request->acknowledged_by_status;
            $pr->acknowledged_date       = $this->normalizeDateTimeInput($request->acknowledged_date);

            $pr->need_asset_code_registration = $request->need_asset_code_registration;
            $pr->action_by_admin              = $request->action_by_admin;
            $pr->action_by_admin_status       = $request->action_by_admin_status;
            $pr->action_by_admin_date         = $this->normalizeDateTimeInput($request->action_by_admin_date);

            $pr->vat = $request->boolean('vat');
            $pr->currency_code = $request->input('currency_code', 'THB');

            $pr->sub_total   = $request->sub_total;
            $pr->vat_value   = $request->vat_value;
            $pr->grand_total = $request->grand_total;

            $pr->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            if ($this->hasCreatedByColumn()) {
                $pr->created_by = (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? ''));
            }
            $pr->save();
            $pr->attachments = $normalizedAttachments;

            // ------- items -------
            foreach ($items as $row) {
                if (is_object($row)) $row = (array)$row;

                if (empty($row['item'])) continue;

                $item = new PurchaseRequisitionItems();
                $item->purchase_requisition_id = $pr->id;
                $item->item        = $row['item'] ?? '';
                $item->description = $row['description'] ?? null;
                $item->quantity    = $row['quantity'] ?? 0;
                $item->unit_price  = $row['unit_price'] ?? 0;
                $item->amount      = $row['amount'] ?? (
                    ($row['quantity'] ?? 0) * ($row['unit_price'] ?? 0)
                );
                $item->create_by   = $loginBy->id ?? 'admin';
                $item->save();
            }

            DB::commit();
            $fresh = $pr->load('items');
            $fresh->permissions = $this->recordPermissionPayload($ctx, $fresh);
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $fresh,
            ]);

        } catch (\Throwable $e) {
            Log::error('PurchaseRequisitions store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // ================= update =================
    public function update(Request $request, $id)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $pr = PurchaseRequisitions::with('items')->find($id);
            if (!$pr) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
            $canEditRecord = $this->canEditRecord($ctx, $pr);
            $workflowAuth = $this->authorizeWorkflowTransition($request, $pr, $ctx);

            if (!$canEditRecord && !($workflowAuth['authorized'] ?? false)) {
                return $this->forbiddenResponse();
            }
            if (($workflowAuth['has_transition'] ?? false) && !($workflowAuth['authorized'] ?? false)) {
                return $this->forbiddenResponse();
            }
            if ($request->has('currency_code') && !in_array($request->currency_code, ['THB', 'USD'])) {
                return $this->returnErrorData('currency_code ต้องเป็น THB หรือ USD', 404);
            }

            if ($canEditRecord) {
                // header เหมือนเดิม เพื่อไม่กระทบ frontend ฝั่ง form/edit
                $pr->to                      = $request->to ?? $pr->to;
                $pr->date                    = $request->date ?? $pr->date;
                $pr->deadline                = $request->deadline;
                $pr->recommended_by          = $request->recommended_by;
                $pr->received_from           = $request->received_from;
                $pr->reasons_for_purchase    = $request->reasons_for_purchase;
                $pr->other_conditions        = $request->other_conditions;
                $pr->quotation_attached      = $request->quotation_attached;

                if ($request->has('attachments')) {
                    $attachments = $request->input('attachments');
                    $normalizedAttachments = $this->normalizeAttachments($attachments);
                    $pr->attachments = $this->encodeAttachments($normalizedAttachments);
                }

                if ($request->has('vat')) {
                    $pr->vat = $request->boolean('vat');
                }

                if ($request->has('currency_code')) {
                    $pr->currency_code = $request->currency_code;
                }

                if ($request->has('sub_total'))   $pr->sub_total   = $request->sub_total;
                if ($request->has('vat_value'))   $pr->vat_value   = $request->vat_value;
                if ($request->has('grand_total')) $pr->grand_total = $request->grand_total;

                $pr->requested_by            = $request->requested_by;
                $pr->requested_by_status     = $request->requested_by_status;
                $pr->requested_date          = $this->normalizeDateTimeInput($request->requested_date);

                $pr->verified_by_is          = $request->verified_by_is;
                $pr->verified_by             = $request->verified_by;
                $pr->approved_by             = $request->approved_by;
                $pr->acknowledged_by         = $request->acknowledged_by;

                $pr->action_by_admin              = $request->action_by_admin;
                $pr->action_by_admin_status       = $request->action_by_admin_status;
                $pr->action_by_admin_date         = $this->normalizeDateTimeInput($request->action_by_admin_date);

                if (($workflowAuth['authorized'] ?? false) && ($workflowAuth['has_transition'] ?? false)) {
                    $this->applyWorkflowOnlyUpdate($pr, $request, $workflowAuth);
                } else {
                    $pr->verified_by_is_status   = $request->verified_by_is_status;
                    $pr->verified_is_date        = $this->normalizeDateTimeInput($request->verified_is_date);
                    $pr->verified_by_status      = $request->verified_by_status;
                    $pr->verified_date           = $this->normalizeDateTimeInput($request->verified_date);
                    $pr->approved_by_status      = $request->approved_by_status;
                    $pr->approved_date           = $this->normalizeDateTimeInput($request->approved_date);
                    $pr->acknowledged_by_status  = $request->acknowledged_by_status;
                    $pr->acknowledged_date       = $this->normalizeDateTimeInput($request->acknowledged_date);
                    $pr->need_asset_code_registration = $request->need_asset_code_registration;
                }
            } else {
                $this->applyWorkflowOnlyUpdate($pr, $request, $workflowAuth);
            }

            $pr->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $pr->save();
            if (isset($normalizedAttachments)) {
                $pr->attachments = $normalizedAttachments;
            }

            // ลบ items เดิม แล้วสร้างใหม่จาก payload (ง่ายสุด)
            if ($canEditRecord && $request->has('items')) {
                PurchaseRequisitionItems::where('purchase_requisition_id', $pr->id)->delete();

                $items = $request->items ?? [];
                foreach ($items as $row) {
                    if (is_object($row)) $row = (array)$row;
                    if (empty($row['item'])) continue;

                    $item = new PurchaseRequisitionItems();
                    $item->purchase_requisition_id = $pr->id;
                    $item->item        = $row['item'] ?? '';
                    $item->description = $row['description'] ?? null;
                    $item->quantity    = $row['quantity'] ?? 0;
                    $item->unit_price  = $row['unit_price'] ?? 0;
                    $item->amount      = $row['amount'] ?? (
                        ($row['quantity'] ?? 0) * ($row['unit_price'] ?? 0)
                    );
                    $item->create_by   = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
                    $item->save();
                }
            }

            DB::commit();
            $fresh = $pr->load('items');
            $fresh->permissions = $this->recordPermissionPayload($ctx, $fresh);
            return $this->returnSuccess('อัปเดตข้อมูลสำเร็จ', [
                'permissions' => $this->modulePermissionPayload($ctx),
                'item' => $fresh,
            ]);

        } catch (\Throwable $e) {
            Log::error('PurchaseRequisitions update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // ================= destroy =================
    public function destroy($id, Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = PurchaseRequisitions::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }
            if (!$this->canDeleteRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            $Item->delete();

            $this->Log(
                $loginBy->employee_code ?? $loginBy->id ?? 'admin',
                "ลบข้อมูล Purchase Requisition #{$id}",
                "ลบข้อมูล"
            );

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }
}
