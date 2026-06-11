<?php

namespace App\Http\Controllers;

use App\Models\ExpensesClaimItems;
use App\Models\ExpensesClaims;
use App\Models\ProjectDetail;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpensesClaimsController extends Controller
{
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
    public function index()
    {
        return $this->getList();
    }

    public function create()
    {
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', [
            'voucher_no' => $this->generateVoucherNo()
        ]);
    }

    public function edit($id)
    {
        return $this->show($id);
    }

    public function getList()
    {
        $items = ExpensesClaims::with('items')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        if (!empty($items)) {
            for ($i = 0; $i < count($items); $i++) {
                $items[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPage(Request $request)
    {
        $length = (int) ($request->length ?? 10);
        if ($length <= 0) {
            $length = 10;
        }
        $start = (int) ($request->start ?? 0);
        $page = (int) floor($start / $length) + 1;
        $order = $request->order ?? [];
        $search = $request->search ?? ['value' => null];

        $col = [
            'id',
            'voucher_no',
            'claimant_name',
            'recive_by',
            'claim_date',
            'total_baht',
            'status',
            'verified_by',
            'verified_by_status',
            'verified_by_date',
            'approved_by',
            'approved_by_status',
            'approved_by_date',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        foreach (['account_by', 'account_by_status', 'account_by_date', 'draft_payload'] as $column) {
            if (Schema::hasColumn('expenses_claims', $column)) {
                $col[] = $column;
            }
        }

        $orderby = [
            '',
            'voucher_no',
            'claimant_name',
            'claim_date',
            'total_baht',
            'status',
            'created_at',
            'updated_at',
        ];

        $query = ExpensesClaims::with('items')->select($col);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('verified_by_status')) {
            $query->where('verified_by_status', $request->verified_by_status);
        }
        if ($request->filled('approved_by_status')) {
            $query->where('approved_by_status', $request->approved_by_status);
        }
        if ($request->filled('account_by_status') && Schema::hasColumn('expenses_claims', 'account_by_status')) {
            $query->where('account_by_status', $request->account_by_status);
        }

        if (!empty($search['value'])) {
            $query->where(function ($q) use ($search, $col) {
                foreach ($col as $c) {
                    $q->orWhere($c, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderColumn = $order[0]['column'] ?? null;
        $orderDir = $order[0]['dir'] ?? 'desc';
        if ($orderColumn !== null && ($orderby[$orderColumn] ?? false)) {
            $query->orderBy($orderby[$orderColumn], $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $data = $query->paginate($length, ['*'], 'page', $page);

        if ($data->isNotEmpty()) {
            $No = (($page - 1) * $length);
            for ($i = 0; $i < count($data); $i++) {
                $No = $No + 1;
                $data[$i]->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    public function show($id)
    {
        $item = ExpensesClaims::with('items')->find($id);
        if (!$item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $item);
    }

    public function getDraft(Request $request)
    {
        if (!$this->hasRequestActor($request)) {
            return $this->unauthorizedDraftResponse();
        }

        $actor = $this->getActorCode($request);
        $draft = ExpensesClaims::with('items')
            ->where('status', 'draft')
            ->where('create_by', $actor)
            ->orderBy('updated_at', 'desc')
            ->first();

        return $this->returnSuccess('เรียกดู Draft สำเร็จ', $draft);
    }

    public function store(Request $request)
    {
        $this->normalizeClaimRequest($request);
        $isDraft = $this->isDraftRequest($request);

        if ($isDraft && !$this->hasRequestActor($request)) {
            return $this->unauthorizedDraftResponse();
        }

        if (!$isDraft) {
            $validation = $this->validateClaimRequest($request);
            if ($validation) {
                return $validation;
            }

            if ($this->voucherNoExists($request->voucher_no)) {
                return $this->duplicateVoucherResponse();
            }
        }

        DB::beginTransaction();

        try {
            $actor = $this->getActorCode($request);
            $claim = new ExpensesClaims();
            $this->fillClaim($claim, $request, $actor, true);
            $this->setDraftPayload($claim, $request, $isDraft);
            $claim->save();

            $this->logDocumentCreateAudit($request, $claim);

            if ($isDraft) {
                $claim->total_baht = (float) ($request->total_baht ?? 0);
                $claim->status = 'draft';
                $claim->save();

                DB::commit();

                return $this->returnSuccess('บันทึก Draft สำเร็จ', $claim->load('items'));
            }

            $total = $this->replaceItems($claim, $request->items ?? [], $actor);
            $claim->total_baht = $total;
            $claim->status = $this->resolveOverallStatus($claim);
            $claim->save();

            DB::commit();

            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $claim->load('items'));
        } catch (QueryException $e) {
            DB::rollBack();
            if ($this->isDuplicateVoucherException($e)) {
                return $this->duplicateVoucherResponse();
            }
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->normalizeClaimRequest($request);
        $isDraft = $this->isDraftRequest($request);

        if ($isDraft && !$this->hasRequestActor($request)) {
            return $this->unauthorizedDraftResponse();
        }

        if (!$isDraft) {
            $validation = $this->validateClaimRequest($request, $id);
            if ($validation) {
                return $validation;
            }

            if ($this->voucherNoExists($request->voucher_no, $id)) {
                return $this->duplicateVoucherResponse();
            }
        }

        DB::beginTransaction();

        try {
            $claim = ExpensesClaims::with('items')->find($id);
            if (!$claim) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            $actor = $this->getActorCode($request);
            $this->fillClaim($claim, $request, $actor, false);
            $this->setDraftPayload($claim, $request, $isDraft);
            $claim->save();

            if ($isDraft) {
                $claim->total_baht = (float) ($request->total_baht ?? 0);
                $claim->status = 'draft';
                $claim->save();

                DB::commit();

                return $this->returnUpdateReturnData('อัปเดต Draft สำเร็จ', $claim->load('items'));
            }

            $total = $this->replaceItems($claim, $request->items ?? [], $actor);
            $claim->total_baht = $total;
            $claim->status = $this->resolveOverallStatus($claim);
            $claim->save();

            DB::commit();

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $claim->load('items'));
        } catch (QueryException $e) {
            DB::rollBack();
            if ($this->isDuplicateVoucherException($e)) {
                return $this->duplicateVoucherResponse();
            }
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $claim = ExpensesClaims::find($id);
            if (!$claim) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            ExpensesClaimItems::where('expenses_claim_id', $claim->id)->delete();
            $claim->delete();

            $actor = $this->getActorCode($request);
            $this->Log($actor, 'ผู้ใช้งาน ' . $actor . ' ได้ทำการลบ Expenses Claim #' . $claim->id, 'ลบ Expenses Claim');

            DB::commit();

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }

    private function validateClaimRequest(Request $request, $id = null)
    {
        if (empty($request->claimant_name)) {
            return $this->returnErrorData('กรุณาระบุ Name (claimant_name)', 404);
        }
        if (empty($request->claim_date)) {
            return $this->returnErrorData('กรุณาระบุ Date (claim_date)', 404);
        }
        if (empty($request->verified_by)) {
            return $this->returnErrorData('กรุณาระบุ Verified by (verified_by)', 404);
        }
        if (empty($request->approved_by)) {
            return $this->returnErrorData('กรุณาระบุ Approved by (approved_by)', 404);
        }
        $items = $request->items ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        foreach ($items as $index => $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            $rowNo = $index + 1;
            if (empty($row['item_date'])) {
                return $this->returnErrorData("กรุณาระบุวันที่ในรายการที่ {$rowNo}", 404);
            }
            if (empty($row['project_detail_id']) && empty($row['project_name'])) {
                return $this->returnErrorData("กรุณาระบุ Project ในรายการที่ {$rowNo}", 404);
            }
            if (!empty($row['project_detail_id'])) {
                if (!ProjectDetail::where('id', $row['project_detail_id'])->exists()) {
                    return $this->returnErrorData("ไม่พบ Project ในรายการที่ {$rowNo}", 404);
                }
            }
            if (empty($row['details'])) {
                return $this->returnErrorData("กรุณาระบุ Details ในรายการที่ {$rowNo}", 404);
            }
            if (!isset($row['baht']) || !is_numeric($row['baht']) || (float) $row['baht'] <= 0) {
                return $this->returnErrorData("กรุณาระบุ Baht มากกว่า 0 ในรายการที่ {$rowNo}", 404);
            }
        }

        return null;
    }

    private function normalizeClaimRequest(Request $request): void
    {
        if ($request->has('voucher_no')) {
            $request->merge([
                'voucher_no' => trim((string) $request->voucher_no),
            ]);
        }
    }

    private function isDraftRequest(Request $request): bool
    {
        return strtolower(trim((string) $request->input('status', ''))) === 'draft';
    }

    private function setDraftPayload(ExpensesClaims $claim, Request $request, bool $isDraft): void
    {
        if (!Schema::hasColumn('expenses_claims', 'draft_payload')) {
            return;
        }

        $claim->draft_payload = $isDraft ? $request->except(['login_by', 'login_id']) : null;
    }

    private function hasRequestActor(Request $request): bool
    {
        if (!empty($request->login_id) || !empty($request->login_by)) {
            return true;
        }

        return $this->getActorCodeFromToken($request) !== null;
    }

    private function unauthorizedDraftResponse()
    {
        return response()->json([
            'code' => '401',
            'status' => false,
            'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน Draft',
            'data' => [],
        ], 401);
    }

    private function voucherNoExists($voucherNo, $exceptId = null): bool
    {
        $voucherNo = trim((string) $voucherNo);
        if ($voucherNo === '') {
            return false;
        }

        $query = ExpensesClaims::withTrashed()
            ->where('voucher_no', $voucherNo);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    private function duplicateVoucherResponse()
    {
        return response()->json([
            'code' => '409',
            'status' => false,
            'message' => 'Voucher # นี้ถูกใช้งานแล้ว กรุณาใช้เลข Voucher ใหม่',
            'data' => [],
        ], 409);
    }

    private function isDuplicateVoucherException(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains((string) $e->getMessage(), 'expenses_claims_voucher_no_unique');
    }

    private function generateVoucherNo(): string
    {
        $prefix = 'EC-' . now()->format('Ymd') . '-';
        $sequence = ExpensesClaims::withTrashed()
            ->where('voucher_no', 'like', $prefix . '%')
            ->count() + 1;

        do {
            $voucherNo = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ExpensesClaims::withTrashed()->where('voucher_no', $voucherNo)->exists());

        return $voucherNo;
    }

    private function fillClaim(ExpensesClaims $claim, Request $request, string $actor, bool $isCreate): void
    {
        $claim->voucher_no = $request->voucher_no ?: ($claim->voucher_no ?: $this->generateVoucherNo());
        $claim->claimant_name = $request->claimant_name ?: ($claim->claimant_name ?: $actor);
        $claim->recive_by = $request->recive_by ?: ($claim->recive_by ?: ($claim->create_by ?: $actor));
        $claim->claim_date = $request->claim_date ?: ($claim->claim_date ?: now()->toDateString());
        $claim->attachments = $this->normalizeAttachments($request->input('attachments', $claim->attachments ?? []));

        $claim->verified_by = $request->verified_by ?: ($claim->verified_by ?? null);
        $claim->verified_by_status = $request->verified_by_status ?? ($claim->verified_by_status ?? 'pending');
        $claim->verified_by_date = $this->normalizeDateTimeInput($request->verified_by_date ?? $claim->verified_by_date);

        $claim->approved_by = $request->approved_by ?: ($claim->approved_by ?? null);
        $claim->approved_by_status = $request->approved_by_status ?? ($claim->approved_by_status ?? 'pending');
        $claim->approved_by_date = $this->normalizeDateTimeInput($request->approved_by_date ?? $claim->approved_by_date);

        if (Schema::hasColumn('expenses_claims', 'account_by')) {
            $claim->account_by = null;
        }
        if (Schema::hasColumn('expenses_claims', 'account_by_status')) {
            $claim->account_by_status = null;
        }
        if (Schema::hasColumn('expenses_claims', 'account_by_date')) {
            $claim->account_by_date = null;
        }

        $claim->status = $request->status ?? ($claim->status ?? 'submitted');

        if ($isCreate) {
            $claim->create_by = $actor;
        } else {
            $claim->update_by = $actor;
        }
    }

    private function replaceItems(ExpensesClaims $claim, array $items, string $actor): float
    {
        ExpensesClaimItems::where('expenses_claim_id', $claim->id)->delete();

        $total = 0.0;
        foreach ($items as $index => $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            $projectDetailId = $row['project_detail_id'] ?? null;
            $project = $projectDetailId ? ProjectDetail::find($projectDetailId) : null;
            $amount = (float) $row['baht'];
            $total += $amount;

            $item = new ExpensesClaimItems();
            $item->expenses_claim_id = $claim->id;
            $item->seq = $row['seq'] ?? ($index + 1);
            $item->item_date = $row['item_date'];
            $item->project_detail_id = $projectDetailId;
            $item->project_code = $project->code ?? ($row['project_code'] ?? null);
            $item->project_name = $project->name ?? ($row['project_name'] ?? null);
            $item->details = $row['details'];
            $item->baht = $amount;
            $item->create_by = $actor;
            $item->save();
        }

        return $total;
    }

    private function resolveOverallStatus(ExpensesClaims $claim): string
    {
        $verified = $this->normalizeWorkflowStatus($claim->verified_by_status);
        $approved = $this->normalizeWorkflowStatus($claim->approved_by_status);
        if ($verified === 'reject' || $approved === 'reject') {
            return 'rejected';
        }
        if ($verified === 'approve' && $approved === 'approve') {
            return 'approved';
        }
        if ($verified === 'approve') {
            return 'verified';
        }

        return 'submitted';
    }

    private function normalizeWorkflowStatus($status): string
    {
        $value = strtolower(trim((string) ($status ?? 'pending')));
        if ($value === 'approved') {
            return 'approve';
        }
        if ($value === 'rejected') {
            return 'reject';
        }
        if ($value === '') {
            return 'pending';
        }

        return $value;
    }

    private function getActorCode(Request $request): string
    {
        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            return $loginBy->employee_code ?? $loginBy->id ?? $loginBy->user_id ?? 'admin';
        }
        if (is_array($loginBy)) {
            return $loginBy['employee_code'] ?? $loginBy['id'] ?? $loginBy['user_id'] ?? 'admin';
        }

        $tokenActor = $this->getActorCodeFromToken($request);
        if ($tokenActor !== null) {
            return $tokenActor;
        }

        $actorId = $this->resolveActorId($request);
        return $actorId !== 'system' ? $actorId : 'admin';
    }

    private function getActorCodeFromToken(Request $request): ?string
    {
        try {
            $header = (string) $request->header('Authorization');
            if ($header === '' || stripos($header, 'Bearer ') !== 0) {
                return null;
            }

            $token = trim(substr($header, 7));
            if ($token === '') {
                return null;
            }

            $payload = \Firebase\JWT\JWT::decode($token, 'key', ['HS256']);
            $loginBy = $payload->lun ?? null;

            if (is_object($loginBy)) {
                $candidate = $loginBy->employee_code ?? $loginBy->id ?? $loginBy->user_id ?? $loginBy->username ?? null;
                return $candidate !== null && $candidate !== '' ? (string) $candidate : null;
            }
            if (is_array($loginBy)) {
                $candidate = $loginBy['employee_code'] ?? $loginBy['id'] ?? $loginBy['user_id'] ?? $loginBy['username'] ?? null;
                return $candidate !== null && $candidate !== '' ? (string) $candidate : null;
            }
            if (isset($payload->aud) && $payload->aud !== null && $payload->aud !== '') {
                return (string) $payload->aud;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
