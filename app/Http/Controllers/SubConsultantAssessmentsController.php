<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\SubConsultantAssessments;
use App\Models\SubConsultantAssessmentFiles;
use App\Models\SubConsultantAssessmentReferences;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubConsultantAssessmentsController extends Controller
{
    private const MENU_KEY = 'sub_consultant_assessments';

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
                    ->orWhere('menus.path', 'like', '%sub_consultant_assessment%')
                    ->orWhere('menus.path', 'like', '%sub-consultant-assessment%');
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

    /**
     * @param  array<int, mixed>  $files
     */
    private function persistAssessmentFiles(int $assessmentId, array $files, $loginBy): void
    {
        $uid = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
        foreach ($files as $file) {
            if (!is_array($file) || empty($file['path'])) {
                continue;
            }
            $row                = new SubConsultantAssessmentFiles();
            $row->assessment_id = $assessmentId;
            $row->name          = $file['name'] ?? null;
            $row->path          = $file['path'];
            $row->create_by     = $uid;
            $row->save();
        }
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

        $query = SubConsultantAssessments::with(['references', 'files'])
            ->orderBy('id', 'desc');
        $this->applyViewScope($query, $ctx);

        $Item = $query
            ->orderBy('id', 'desc')
            ->get();

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

        $Status  = $request->status;
        $assessedStatus = $request->assessed_by_status;
        $approvedStatus = $request->approved_by_status;
        $ackStatus = $request->acknowledged_by_status;

        // คอลัมน์ที่ select (เอาที่ใช้จริงบนหน้า list)
        $col = array(
            'id',
            'form_code',
            'to',
            'circ',
            'company',
            'item1_total_score',
            'recommendation',
            'status',
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
        );

        // mapping สำหรับ sort (DataTables column index)
        $orderby = array(
            '',
            'form_code',
            'to',
            'circ',
            'company',
            'item1_total_score',
            'recommendation',
            'status',
            'created_at',
        );

        $D = SubConsultantAssessments::with('files')->select($col);
        $this->applyViewScope($D, $ctx);

        if (isset($Status)) {
            $D->where('status', $Status);
        }

        // Filter by status fields (optional)
        if (!empty($assessedStatus)) {
            $D->where('assessed_by_status', $assessedStatus);
        }
        if (!empty($approvedStatus)) {
            $D->where('approved_by_status', $approvedStatus);
        }
        if (!empty($ackStatus)) {
            $D->where('acknowledged_by_status', $ackStatus);
        }

        if ($orderby[$order[0]['column']] ?? false) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        } else {
            $D->orderBy('id', 'desc');
        }

        if ($search['value'] != '' && $search['value'] != null) {
            $D->where(function ($query) use ($search, $col) {
                $query->orWhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
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

        $Item = SubConsultantAssessments::with(['references', 'files'])->find($id);

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

        // ===== Validate Required =====
        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ To (to)', 404);
        }
        if (!isset($request->circ)) {
            return $this->returnErrorData('กรุณาระบุ Circ (circ)', 404);
        }
        if (!isset($request->scope_of_service)) {
            return $this->returnErrorData('กรุณาระบุ Scope of Service (scope_of_service)', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ Company (company)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new SubConsultantAssessments();

            // Document info
            $Item->form_code  = $request->form_code ?? null;
            $Item->form_title = $request->form_title ?? null;

            // Header
            $Item->to              = $request->to;
            $Item->circ            = $request->circ;
            $Item->scope_of_service = $request->scope_of_service ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile_biodata       = $request->info_company_profile_biodata ?? null;
            $Item->info_site_visit                    = $request->info_site_visit ?? null;
            $Item->info_previous_evaluation_record    = $request->info_previous_evaluation_record ?? null;
            $Item->info_project_reference_certificates= $request->info_project_reference_certificates ?? null;
            $Item->info_previous_assessment_record    = $request->info_previous_assessment_record ?? null;
            $Item->info_iso_certificates              = $request->info_iso_certificates ?? null;

            // Item 1
            $Item->company = $request->company;

            $Item->score_experience_since_establishment = $request->score_experience_since_establishment ?? 0;
            $Item->score_fully_qualified_staff         = $request->score_fully_qualified_staff ?? 0;
            $Item->score_completed_similar_projects    = $request->score_completed_similar_projects ?? 0;

            // optional total (ถ้าไม่ส่งมา จะคำนวณให้)
            $Item->item1_total_score = $request->item1_total_score ?? (
                (int)($Item->score_experience_since_establishment ?? 0) +
                (int)($Item->score_fully_qualified_staff ?? 0) +
                (int)($Item->score_completed_similar_projects ?? 0)
            );

            // Item 2
            $Item->ems_iso_14001   = $request->ems_iso_14001 ?? null;
            $Item->ems_ohsas_18001 = $request->ems_ohsas_18001 ?? null;
            $Item->ems_iso_45001   = $request->ems_iso_45001 ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null; // accept | not_accept
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Decision #3
            $Item->decision_sub_consultant_list = $request->decision_sub_consultant_list ?? null;

            // Remark
            $Item->remark = $request->remark ?? null;

            // Signatures/Approval — POST: วันที่ประเมิน (assessed_by_date) ให้ตรงกับ created_at (หลัง save)
            $Item->assessed_by     = $request->assessed_by ?? null;
            $Item->assessed_by_status = $request->assessed_by_status ?? 'pending';

            $Item->approved_by     = $request->approved_by ?? null;
            $Item->approved_by_date   = $this->normalizeDateTimeInput($request->approved_date ?? $request->approved_by_date ?? null);
            $Item->approved_by_status = $request->approved_by_status ?? 'pending';

            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date   = $this->normalizeDateTimeInput($request->acknowledged_date ?? $request->acknowledged_by_date ?? null);
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? 'pending';

            // Overall status
            $Item->status = $request->status ?? 'draft';

            // Control
            $Item->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $Item->created_by = (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? ''));

            $Item->save();

            $Item->assessed_by_date = $Item->created_at;
            $Item->timestamps = false;
            $Item->save();
            $Item->timestamps = true;

            // ===== References (Item 3) =====
            // รูปแบบที่รองรับ: references: [{seq:1, reference_name:"", opinion:"good"}, ...]
            $refs = $request->references ?? [];
            if (is_array($refs) && count($refs) > 0) {
                foreach ($refs as $r) {
                    $Ref = new SubConsultantAssessmentReferences();
                    $Ref->assessment_id   = $Item->id;
                    $Ref->seq             = $r['seq'] ?? null;
                    $Ref->reference_name  = $r['reference_name'] ?? null;
                    $Ref->opinion         = $r['opinion'] ?? null;
                    $Ref->create_by       = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
                    $Ref->save();
                }
            }

            $fileRows = $request->input('files');
            if (is_array($fileRows) && count($fileRows) > 0) {
                $this->persistAssessmentFiles((int) $Item->id, $fileRows, $loginBy);
            }

            DB::commit();

            $Item = SubConsultantAssessments::with(['references', 'files'])->find($Item->id);
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

        // ===== Validate Required =====
        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ To (to)', 404);
        }
        if (!isset($request->circ)) {
            return $this->returnErrorData('กรุณาระบุ Circ (circ)', 404);
        }
        if (!isset($request->scope_of_service)) {
            return $this->returnErrorData('กรุณาระบุ Scope of Service (scope_of_service)', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ Company (company)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = SubConsultantAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }
            if (!$this->canEditRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            // Document info
            $Item->form_code  = $request->form_code ?? $Item->form_code;
            $Item->form_title = $request->form_title ?? $Item->form_title;

            // Header
            $Item->to               = $request->to;
            $Item->circ             = $request->circ;
            $Item->scope_of_service = $request->scope_of_service ?? null;

            // Info used
            $Item->info_company_profile_biodata        = $request->info_company_profile_biodata ?? null;
            $Item->info_site_visit                     = $request->info_site_visit ?? null;
            $Item->info_previous_evaluation_record     = $request->info_previous_evaluation_record ?? null;
            $Item->info_project_reference_certificates = $request->info_project_reference_certificates ?? null;
            $Item->info_previous_assessment_record     = $request->info_previous_assessment_record ?? null;
            $Item->info_iso_certificates               = $request->info_iso_certificates ?? null;

            // Item 1
            $Item->company = $request->company;

            $Item->score_experience_since_establishment = $request->score_experience_since_establishment ?? 0;
            $Item->score_fully_qualified_staff          = $request->score_fully_qualified_staff ?? 0;
            $Item->score_completed_similar_projects     = $request->score_completed_similar_projects ?? 0;

            $Item->item1_total_score = $request->item1_total_score ?? (
                (int)($Item->score_experience_since_establishment ?? 0) +
                (int)($Item->score_fully_qualified_staff ?? 0) +
                (int)($Item->score_completed_similar_projects ?? 0)
            );

            // Item 2
            $Item->ems_iso_14001   = $request->ems_iso_14001 ?? null;
            $Item->ems_ohsas_18001 = $request->ems_ohsas_18001 ?? null;
            $Item->ems_iso_45001   = $request->ems_iso_45001 ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? $Item->recommendation;
            $Item->recommendation_reason = $request->recommendation_reason ?? $Item->recommendation_reason;

            // Decision #3
            $Item->decision_sub_consultant_list = $request->decision_sub_consultant_list ?? $Item->decision_sub_consultant_list;

            // Remark
            $Item->remark = $request->remark ?? $Item->remark;

            // Signatures/Approval (รองรับทั้ง assessed_date และ assessed_by_date จาก Frontend)
            $Item->assessed_by     = $request->assessed_by ?? $Item->assessed_by;
            $Item->assessed_by_date   = $this->normalizeDateTimeInput($request->assessed_date ?? $request->assessed_by_date ?? $Item->assessed_by_date);
            $Item->assessed_by_status = $request->assessed_by_status ?? $Item->assessed_by_status ?? 'pending';

            $Item->approved_by     = $request->approved_by ?? $Item->approved_by;
            $Item->approved_by_date   = $this->normalizeDateTimeInput($request->approved_date ?? $request->approved_by_date ?? $Item->approved_by_date);
            $Item->approved_by_status = $request->approved_by_status ?? $Item->approved_by_status ?? 'pending';

            $Item->acknowledged_by     = $request->acknowledged_by ?? $Item->acknowledged_by;
            $Item->acknowledged_by_date   = $this->normalizeDateTimeInput($request->acknowledged_date ?? $request->acknowledged_by_date ?? $Item->acknowledged_by_date);
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? $Item->acknowledged_by_status ?? 'pending';

            // Overall status
            $Item->status = $request->status ?? $Item->status;

            // Control
            $Item->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';

            $Item->save();

            // ===== References: ล้างของเก่าแล้วใส่ใหม่ (ง่ายและชัวร์) =====
            SubConsultantAssessmentReferences::where('assessment_id', $Item->id)->delete();

            $refs = $request->references ?? [];
            if (is_array($refs) && count($refs) > 0) {
                foreach ($refs as $r) {
                    $Ref = new SubConsultantAssessmentReferences();
                    $Ref->assessment_id   = $Item->id;
                    $Ref->seq             = $r['seq'] ?? null;
                    $Ref->reference_name  = $r['reference_name'] ?? null;
                    $Ref->opinion         = $r['opinion'] ?? null;
                    $Ref->create_by       = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
                    $Ref->save();
                }
            }

            // ----- ไฟล์แนบ: ถ้ามี key files ใน body ให้แทนที่ชุดทั้งหมด (ว่าง = ลบทั้งหมด) -----
            if ($request->exists('files')) {
                SubConsultantAssessmentFiles::where('assessment_id', $Item->id)->delete();
                $fileRows = $request->input('files');
                if (is_array($fileRows) && count($fileRows) > 0) {
                    $this->persistAssessmentFiles((int) $Item->id, $fileRows, $loginBy);
                }
            }

            DB::commit();

            $Item = SubConsultantAssessments::with(['references', 'files'])->find($Item->id);
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

            $Item = SubConsultantAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }
            if (!$this->canDeleteRecord($ctx, $Item)) {
                return $this->forbiddenResponse();
            }

            // delete children first (soft delete)
            SubConsultantAssessmentFiles::where('assessment_id', $Item->id)->delete();
            SubConsultantAssessmentReferences::where('assessment_id', $Item->id)->delete();

            $Item->delete();

            // log
            $userId      = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $type        = 'ลบแบบประเมินผู้รับเหมาช่วง';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' #' . $Item->id;
            $this->Log($userId, $description, $type);

            DB::commit();

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {

            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }
}
