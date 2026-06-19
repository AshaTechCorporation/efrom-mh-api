<?php
namespace App\Http\Controllers;

use App\Models\PurchaseRequisitions;
use App\Models\PurchaseRequisitionItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseRequisitionsController extends Controller
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SUBMITTED = 'submitted';
    private const DEFAULT_PAYMENT_TERM = 'Accept invoices only at the end of each month. Payment will be made at the end of the following month after the invoice is received.';

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

    private function validateSecondApproverForTotal(Request $request, $existingGrandTotal = null)
    {
        $grandTotal = $request->has('grand_total')
            ? (float) $request->grand_total
            : (float) ($existingGrandTotal ?? 0);

        if ($grandTotal <= 50000) {
            return null;
        }

        $approvedBy = trim((string) $request->approved_by);
        $approvedBy2 = trim((string) $request->approved_by_2);

        if ($approvedBy2 === '') {
            return $this->returnErrorData('กรุณาระบุ approved_by_2 เมื่อยอดรวมเกิน 50,000', 404);
        }

        if ($approvedBy !== '' && $approvedBy === $approvedBy2) {
            return $this->returnErrorData('approved_by_2 ต้องไม่ซ้ำกับ approved_by', 404);
        }

        return null;
    }

    private function normalizeBooleanFlag($value)
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function normalizeDocumentStatus($value): string
    {
        $status = strtolower(trim((string) $value));

        if ($status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        return self::STATUS_SUBMITTED;
    }

    private function isDraftStatus($status): bool
    {
        return $this->normalizeDocumentStatus($status) === self::STATUS_DRAFT;
    }

    private function requiredFieldMissing($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function validatePurchaseRequisitionRequest(Request $request, bool $isDraft): ?JsonResponse
    {
        if ($isDraft) {
            return null;
        }

        if ($this->requiredFieldMissing($request->to)) {
            return $this->returnErrorData('กรุณาระบุ to', 404);
        }
        if ($this->requiredFieldMissing($request->date)) {
            return $this->returnErrorData('กรุณาระบุ date', 404);
        }

        $items = $request->items ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        return null;
    }

    private function draftString($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableDateTime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeDateTimeInput($value);
    }

    private function requestDateOrToday($value): string
    {
        $date = $this->nullableDateTime($value);

        if ($date === null || $date === '') {
            return now()->toDateString();
        }

        return $date;
    }

    private function applyDraftWorkflowDefaults(PurchaseRequisitions $pr): void
    {
        $pr->requested_by_status = null;
        $pr->requested_date = null;
        $pr->verified_by_is_status = null;
        $pr->verified_is_date = null;
        $pr->verified_by_status = null;
        $pr->verified_date = null;
        $pr->approved_by_status = null;
        $pr->approved_date = null;
        $pr->approved_by_2_status = null;
        $pr->approved_by_2_date = null;
        $pr->acknowledged_by_status = null;
        $pr->acknowledged_date = null;
        $pr->action_by_admin_status = null;
        $pr->action_by_admin_date = null;
    }

    private function shouldSkipPurchaseRequisitionItem(array $row, bool $isDraft): bool
    {
        $description = trim((string) ($row['description'] ?? ''));

        if ($isDraft && $description === '') {
            return true;
        }

        return empty($row['item']) && $description === '';
    }

    private function hasWorkflowAssignee($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function isPositiveNumber($value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }

    private function isNonNegativeNumber($value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    private function validateStoredPurchaseRequisitionForSubmit(PurchaseRequisitions $pr): ?JsonResponse
    {
        if ($this->requiredFieldMissing($pr->to)) {
            return $this->returnErrorData('กรุณาระบุ to', 404);
        }
        if ($this->requiredFieldMissing($pr->date)) {
            return $this->returnErrorData('กรุณาระบุ date', 404);
        }
        if ($this->requiredFieldMissing($pr->currency_code)) {
            return $this->returnErrorData('กรุณาระบุ currency_code', 404);
        }
        if ($this->requiredFieldMissing($pr->reasons_for_purchase)) {
            return $this->returnErrorData('กรุณาระบุ reasons_for_purchase', 404);
        }
        if (strlen(trim((string) $pr->reasons_for_purchase)) < 10) {
            return $this->returnErrorData('reasons_for_purchase ต้องมีอย่างน้อย 10 ตัวอักษร', 404);
        }
        if (!$this->hasWorkflowAssignee($pr->requested_by)) {
            return $this->returnErrorData('กรุณาระบุ requested_by', 404);
        }
        if (!$this->hasWorkflowAssignee($pr->verified_by)) {
            return $this->returnErrorData('กรุณาระบุ verified_by', 404);
        }
        if (!$this->hasWorkflowAssignee($pr->approved_by)) {
            return $this->returnErrorData('กรุณาระบุ approved_by', 404);
        }
        if (!$this->hasWorkflowAssignee($pr->acknowledged_by)) {
            return $this->returnErrorData('กรุณาระบุ acknowledged_by', 404);
        }
        if (!$this->hasWorkflowAssignee($pr->action_by_admin)) {
            return $this->returnErrorData('กรุณาระบุ action_by_admin', 404);
        }

        $grandTotal = (float) ($pr->grand_total ?? 0);
        if ($grandTotal > 50000) {
            if (!$this->hasWorkflowAssignee($pr->approved_by_2)) {
                return $this->returnErrorData('กรุณาระบุ approved_by_2 เมื่อยอดรวมเกิน 50,000', 404);
            }
            if ($this->hasWorkflowAssignee($pr->approved_by) && trim((string) $pr->approved_by) === trim((string) $pr->approved_by_2)) {
                return $this->returnErrorData('approved_by_2 ต้องไม่ซ้ำกับ approved_by', 404);
            }
        }

        if ($pr->items->count() === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        foreach ($pr->items as $index => $item) {
            $rowNo = $index + 1;
            if ($this->requiredFieldMissing($item->description)) {
                return $this->returnErrorData("กรุณาระบุ description ในรายการที่ {$rowNo}", 404);
            }
            if (!$this->isPositiveNumber($item->quantity)) {
                return $this->returnErrorData("กรุณาระบุ quantity ในรายการที่ {$rowNo}", 404);
            }
            if (!$this->isNonNegativeNumber($item->unit_price)) {
                return $this->returnErrorData("กรุณาระบุ unit_price ในรายการที่ {$rowNo}", 404);
            }
        }

        return null;
    }

    private function applySubmittedWorkflowDefaults(PurchaseRequisitions $pr): void
    {
        $pr->requested_by_status = $this->hasWorkflowAssignee($pr->requested_by) ? 'pending' : null;
        $pr->requested_date = null;
        $pr->verified_by_is_status = $this->hasWorkflowAssignee($pr->verified_by_is) ? 'pending' : null;
        $pr->verified_is_date = null;
        $pr->verified_by_status = $this->hasWorkflowAssignee($pr->verified_by) ? 'pending' : null;
        $pr->verified_date = null;
        $pr->approved_by_status = $this->hasWorkflowAssignee($pr->approved_by) ? 'pending' : null;
        $pr->approved_date = null;
        $pr->approved_by_2_status = $this->hasWorkflowAssignee($pr->approved_by_2) ? 'pending' : null;
        $pr->approved_by_2_date = null;
        $pr->acknowledged_by_status = $this->hasWorkflowAssignee($pr->acknowledged_by) ? 'pending' : null;
        $pr->acknowledged_date = null;
        $pr->action_by_admin_status = null;
        $pr->action_by_admin_date = null;
    }

    private function workflowApprovedValues(): array
    {
        return ['approve', 'approved', 'APPROVE', 'APPROVED', 'Approve', 'Approved'];
    }

    private function workflowRejectedValues(): array
    {
        return ['reject', 'rejected', 'REJECT', 'REJECTED', 'Reject', 'Rejected'];
    }

    private function workflowPendingValues(): array
    {
        return ['pending', 'PENDING', 'Pending'];
    }

    private function purchaseRequisitionWorkflowSteps(): array
    {
        return [
            ['by' => 'verified_by_is', 'status' => 'verified_by_is_status'],
            ['by' => 'verified_by', 'status' => 'verified_by_status'],
            ['by' => 'approved_by', 'status' => 'approved_by_status'],
            ['by' => 'approved_by_2', 'status' => 'approved_by_2_status'],
            ['by' => 'acknowledged_by', 'status' => 'acknowledged_by_status'],
        ];
    }

    private function applyApprovedPurchaseRequisitionFilter($query): void
    {
        $approved = $this->workflowApprovedValues();

        $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', self::STATUS_DRAFT);
        });

        foreach ($this->purchaseRequisitionWorkflowSteps() as $step) {
            $query->where(function ($q) use ($step, $approved) {
                $q->whereNull($step['by'])
                    ->orWhere($step['by'], '')
                    ->orWhereIn($step['status'], $approved);
            });
        }

        $query->where(function ($q) {
            $q->whereNull('action_by_admin')
                ->orWhere('action_by_admin', '')
                ->orWhereNotNull('action_by_admin_date');
        });

        $query->where(function ($q) {
            foreach ($this->purchaseRequisitionWorkflowSteps() as $step) {
                $q->orWhere(function ($sub) use ($step) {
                    $sub->whereNotNull($step['by'])
                        ->where($step['by'], '!=', '');
                });
            }

            $q->orWhere(function ($sub) {
                $sub->whereNotNull('action_by_admin')
                    ->where('action_by_admin', '!=', '');
            });
        });
    }

    private function applyRejectedPurchaseRequisitionFilter($query): void
    {
        $rejected = $this->workflowRejectedValues();

        $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', self::STATUS_DRAFT);
        });

        $query->where(function ($q) use ($rejected) {
            foreach ($this->purchaseRequisitionWorkflowSteps() as $step) {
                $q->orWhereIn($step['status'], $rejected);
            }
        });
    }

    private function applyPendingPurchaseRequisitionFilter($query): void
    {
        $rejected = $this->workflowRejectedValues();
        $approved = $this->workflowApprovedValues();
        $pending = $this->workflowPendingValues();

        $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', self::STATUS_DRAFT);
        });

        $query->where(function ($q) use ($rejected) {
            foreach ($this->purchaseRequisitionWorkflowSteps() as $step) {
                $q->where(function ($sub) use ($step, $rejected) {
                    $sub->whereNull($step['status'])
                        ->orWhereNotIn($step['status'], $rejected);
                });
            }
        });

        $query->where(function ($q) use ($approved, $pending) {
            foreach ($this->purchaseRequisitionWorkflowSteps() as $step) {
                $q->orWhere(function ($sub) use ($step, $approved, $pending) {
                    $sub->whereNotNull($step['by'])
                        ->where($step['by'], '!=', '')
                        ->where(function ($statusQuery) use ($step, $approved, $pending) {
                            $statusQuery->whereNull($step['status'])
                                ->orWhere($step['status'], '')
                                ->orWhereIn($step['status'], $pending)
                                ->orWhereNotIn($step['status'], $approved);
                        });
                });
            }

            $q->orWhere(function ($sub) {
                $sub->whereNotNull('action_by_admin')
                    ->where('action_by_admin', '!=', '')
                    ->whereNull('action_by_admin_date');
            });
        });
    }

    private function applyPurchaseRequisitionRequestFilters($query, Request $request, array $columns): void
    {
        $search = $request->input('search.value');
        if (!empty($search)) {
            $keyword = '%' . $search . '%';
            $query->where(function ($q) use ($keyword, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $keyword);
                }
            });
        }

        $filters = $request->input('filters', []);
        $status = '';
        if (is_array($filters)) {
            $status = strtolower(trim((string) ($filters['status'] ?? $filters['workflowStatus'] ?? '')));
        }

        if ($status === '') {
            $status = strtolower(trim((string) ($request->approved_by_status ?? '')));
        }

        if ($status === 'approved') {
            $status = 'approve';
        } elseif ($status === 'rejected') {
            $status = 'reject';
        }

        if ($status === self::STATUS_DRAFT) {
            $query->where('status', self::STATUS_DRAFT);
        } elseif ($status === 'approve') {
            $this->applyApprovedPurchaseRequisitionFilter($query);
        } elseif ($status === 'reject') {
            $this->applyRejectedPurchaseRequisitionFilter($query);
        } elseif ($status === 'pending') {
            $this->applyPendingPurchaseRequisitionFilter($query);
        }
    }

    // ================= getList =================
    public function getList()
    {
        $Item = PurchaseRequisitions::with('items')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        foreach ($Item as $i => $v) {
            $Item[$i]['No'] = $i + 1;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // ================= getPage (DataTable) =================
    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length  = $request->length ?? 10;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start ?? 0;
        $page    = floor($start / $length) + 1;

        $col = [
            'id',
            'status',
            'to',
            'subject',
            'date',
            'deadline',
            'attachments',
            'recommended_by',
            'vat',
            'currency_code',
            'received_from',
            'reasons_for_purchase',
            'other_conditions',
            'payment_term',
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
            'approved_by_2',
            'approved_by_2_status',
            'approved_by_2_date',
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
            'discount',
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

        $this->applyPurchaseRequisitionRequestFilters($D, $request, $col);

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
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    // ================= show =================
    public function show($id)
    {
        $Item = PurchaseRequisitions::with('items')->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบข้อมูลที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // ================= store =================
    public function store(Request $request)
    {
        $loginBy = $request->login_by;
        $status = $this->normalizeDocumentStatus($request->input('status', self::STATUS_SUBMITTED));
        $isDraft = $this->isDraftStatus($status);

        if ($validationError = $this->validatePurchaseRequisitionRequest($request, $isDraft)) {
            return $validationError;
        }

        if (!$isDraft && ($error = $this->validateSecondApproverForTotal($request))) {
            return $error;
        }

        DB::beginTransaction();

        try {
            $pr = new PurchaseRequisitions();
            $pr->status                  = $status;
            $pr->to                      = $isDraft ? $this->draftString($request->to ?? null) : $request->to;
            $pr->subject                 = $request->subject;
            $pr->date                    = $this->requestDateOrToday($request->date ?? null);
            $pr->deadline                = $request->deadline;
            $pr->recommended_by          = $request->recommended_by;
            $pr->received_from           = $request->received_from;
            $pr->reasons_for_purchase    = $request->reasons_for_purchase;
            $pr->other_conditions        = $request->other_conditions;
            $pr->payment_term            = $request->has('payment_term') ? $request->payment_term : self::DEFAULT_PAYMENT_TERM;
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

            if ((float) $request->grand_total > 50000) {
                $pr->approved_by_2        = $request->approved_by_2;
                $pr->approved_by_2_status = $request->approved_by_2_status ?? 'pending';
                $pr->approved_by_2_date   = $this->normalizeDateTimeInput($request->approved_by_2_date);
            }

            $pr->acknowledged_by         = $request->acknowledged_by;
            $pr->acknowledged_by_status  = $request->acknowledged_by_status;
            $pr->acknowledged_date       = $this->normalizeDateTimeInput($request->acknowledged_date);

            $pr->need_asset_code_registration = $request->need_asset_code_registration;
            $pr->action_by_admin              = $request->action_by_admin;
            $pr->action_by_admin_status       = $request->action_by_admin_status;
            $pr->action_by_admin_date         = $this->normalizeDateTimeInput($request->action_by_admin_date);

            if ($isDraft) {
                $this->applyDraftWorkflowDefaults($pr);
            }

            $pr->vat = $request->boolean('vat');
            $pr->currency_code = $this->normalizeCurrencyCodeInput($request->input('currency_code'));

            $pr->sub_total   = $request->sub_total;
            $pr->vat_value   = $request->vat_value;
            $pr->discount    = $request->discount ?? 0;
            $pr->grand_total = $request->grand_total;

            $pr->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $pr->save();
            $pr->attachments = $normalizedAttachments;

            // ------- items -------
            foreach (($request->items ?? []) as $row) {
                if (is_object($row)) $row = (array)$row;

                if (!is_array($row) || $this->shouldSkipPurchaseRequisitionItem($row, $isDraft)) {
                    continue;
                }

                $item = new PurchaseRequisitionItems();
                $item->purchase_requisition_id = $pr->id;
                $item->item        = $row['item'] ?? '';
                $item->description = $row['description'] ?? null;
                $item->quantity    = $row['quantity'] ?? 0;
                $item->unit_price  = $row['unit_price'] ?? 0;
                $item->amount      = $row['amount'] ?? (
                    ($row['quantity'] ?? 0) * ($row['unit_price'] ?? 0)
                );
                $item->need_asset_code_registration = $this->normalizeBooleanFlag($row['need_asset_code_registration'] ?? false);
                $item->create_by   = $loginBy->id ?? 'admin';
                $item->save();
            }

            $this->logDocumentCreateAudit($request, $pr);

            DB::commit();
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $pr->load('items'));

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
        $loginBy = $request->login_by;
        $status = $this->normalizeDocumentStatus($request->input('status', self::STATUS_SUBMITTED));
        $isDraft = $this->isDraftStatus($status);

        DB::beginTransaction();

        try {
            $pr = PurchaseRequisitions::with('items')->find($id);
            if (!$pr) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $oldActionValues = $this->purchaseRequisitionActionValues($pr);

            if ($validationError = $this->validatePurchaseRequisitionRequest($request, $isDraft)) {
                DB::rollBack();
                return $validationError;
            }

            if (!$isDraft && ($error = $this->validateSecondApproverForTotal($request, $pr->grand_total))) {
                DB::rollBack();
                return $error;
            }

            // header เหมือน store (เช็ค required ตามที่ต้องการเองได้)
            $pr->status                  = $status;
            $pr->to                      = $isDraft ? $this->draftString($request->to ?? null) : ($request->to ?? $pr->to);
            $pr->subject                 = $request->has('subject') ? $request->subject : $pr->subject;
            $pr->date                    = $isDraft ? $this->requestDateOrToday($request->date ?? null) : ($request->date ?? $pr->date);
            $pr->deadline                = $request->deadline;
            $pr->recommended_by          = $request->recommended_by;
            $pr->received_from           = $request->received_from;
            $pr->reasons_for_purchase    = $request->reasons_for_purchase;
            $pr->other_conditions        = $request->other_conditions;
            if ($request->has('payment_term')) {
                $pr->payment_term = $request->payment_term;
            } elseif ($pr->payment_term === null) {
                $pr->payment_term = self::DEFAULT_PAYMENT_TERM;
            }
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
                $pr->currency_code = $this->normalizeCurrencyCodeInput($request->currency_code, $pr->currency_code ?? 'THB');
            }

            if ($request->has('sub_total'))   $pr->sub_total   = $request->sub_total;
            if ($request->has('vat_value'))   $pr->vat_value   = $request->vat_value;
            if ($request->has('discount'))    $pr->discount    = $request->discount ?? 0;
            if ($request->has('grand_total')) $pr->grand_total = $request->grand_total;

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

            $effectiveGrandTotal = $request->has('grand_total') ? (float) $request->grand_total : (float) $pr->grand_total;
            if ($effectiveGrandTotal > 50000) {
                $pr->approved_by_2        = $request->approved_by_2;
                $pr->approved_by_2_status = $request->approved_by_2_status ?? $pr->approved_by_2_status ?? 'pending';
                $pr->approved_by_2_date   = $this->normalizeDateTimeInput($request->approved_by_2_date ?? $pr->approved_by_2_date);
            } else {
                $pr->approved_by_2        = null;
                $pr->approved_by_2_status = null;
                $pr->approved_by_2_date   = null;
            }

            $pr->acknowledged_by         = $request->acknowledged_by;
            $pr->acknowledged_by_status  = $request->acknowledged_by_status;
            $pr->acknowledged_date       = $this->normalizeDateTimeInput($request->acknowledged_date);

            $pr->need_asset_code_registration = $request->need_asset_code_registration;
            $pr->action_by_admin              = $request->action_by_admin;
            $pr->action_by_admin_status       = $request->action_by_admin_status;
            $pr->action_by_admin_date         = $this->normalizeDateTimeInput($request->action_by_admin_date);

            if ($isDraft) {
                $this->applyDraftWorkflowDefaults($pr);
            }

            $pr->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $pr->save();

            $this->logPurchaseRequisitionActionChanges($request, $pr, $oldActionValues);

            if (isset($normalizedAttachments)) {
                $pr->attachments = $normalizedAttachments;
            }

            // ลบ items เดิม แล้วสร้างใหม่จาก payload (ง่ายสุด)
            if ($request->has('items')) {
                PurchaseRequisitionItems::where('purchase_requisition_id', $pr->id)->delete();

                $items = $request->items ?? [];
                foreach ($items as $row) {
                    if (is_object($row)) $row = (array)$row;
                    if (!is_array($row) || $this->shouldSkipPurchaseRequisitionItem($row, $isDraft)) {
                        continue;
                    }

                    $item = new PurchaseRequisitionItems();
                    $item->purchase_requisition_id = $pr->id;
                    $item->item        = $row['item'] ?? '';
                    $item->description = $row['description'] ?? null;
                    $item->quantity    = $row['quantity'] ?? 0;
                    $item->unit_price  = $row['unit_price'] ?? 0;
                    $item->amount      = $row['amount'] ?? (
                        ($row['quantity'] ?? 0) * ($row['unit_price'] ?? 0)
                    );
                    $item->need_asset_code_registration = $this->normalizeBooleanFlag($row['need_asset_code_registration'] ?? false);
                    $item->create_by   = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
                    $item->save();
                }
            }

            DB::commit();
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $pr->load('items'));

        } catch (\Throwable $e) {
            Log::error('PurchaseRequisitions update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    private function purchaseRequisitionActionColumns(): array
    {
        return [
            'requested_by_status',
            'verified_by_is_status',
            'verified_by_status',
            'approved_by_status',
            'approved_by_2_status',
            'acknowledged_by_status',
            'action_by_admin_status',
        ];
    }

    private function purchaseRequisitionActionValues(PurchaseRequisitions $pr): array
    {
        $values = [];
        foreach ($this->purchaseRequisitionActionColumns() as $column) {
            $values[$column] = $pr->{$column} ?? null;
        }

        return $values;
    }

    private function logPurchaseRequisitionActionChanges(Request $request, PurchaseRequisitions $pr, array $oldValues): void
    {
        foreach ($this->purchaseRequisitionActionColumns() as $column) {
            $oldValue = $oldValues[$column] ?? null;
            $newValue = $pr->{$column} ?? null;

            if (strtolower(trim((string) $oldValue)) === strtolower(trim((string) $newValue))) {
                continue;
            }

            $this->logActionRequestAudit(
                $request,
                'purchase_requisitions',
                $pr->id,
                $column,
                $oldValue,
                $newValue,
                $request->input('comments') ?? $request->input('comment') ?? null
            );
        }
    }

    public function submit($id, Request $request)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $pr = PurchaseRequisitions::with('items')->find($id);
            if (!$pr) {
                DB::rollBack();
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการส่งอนุมัติ', 404);
            }

            if ($validationError = $this->validateStoredPurchaseRequisitionForSubmit($pr)) {
                DB::rollBack();
                return $validationError;
            }

            $pr->status = self::STATUS_SUBMITTED;
            $pr->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $this->applySubmittedWorkflowDefaults($pr);
            $pr->save();

            DB::commit();
            return $this->returnUpdateReturnData('ส่งอนุมัติสำเร็จ', $pr->load('items'));

        } catch (\Throwable $e) {
            Log::error('PurchaseRequisitions submit failed', [
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
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = PurchaseRequisitions::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
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
