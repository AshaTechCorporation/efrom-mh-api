<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ControlledDocumentRequests;

class ControlledDocumentRequestsController extends Controller
{
    private const CDR_SEQUENCE_TABLE = 'controlled_document_request_number_sequences';
    private const CDR_SEQUENCE_PREFIX = 'cdr_';
    private const CDR_START_YEAR = 2026;
    private const CDR_START_SEQUENCE = 3;
    private const CDR_PAD_LENGTH = 3;

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

    private function encodeAttachments($normalized)
    {
        if (empty($normalized)) {
            return null;
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    private function nextCdrNo($date = null): string
    {
        $year = $this->cdrYear($date);
        $sequenceKey = $this->cdrSequenceKey($year);
        $initialLastNumber = $this->initialLastNumberForYear($year);

        DB::table(self::CDR_SEQUENCE_TABLE)->insertOrIgnore([
            'document_key' => $sequenceKey,
            'last_number' => $initialLastNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequenceRow = DB::table(self::CDR_SEQUENCE_TABLE)
            ->where('document_key', $sequenceKey)
            ->lockForUpdate()
            ->first();

        $nextSequence = max((int) ($sequenceRow->last_number ?? 0), $initialLastNumber) + 1;

        DB::table(self::CDR_SEQUENCE_TABLE)
            ->where('document_key', $sequenceKey)
            ->update([
                'last_number' => $nextSequence,
                'updated_at' => now(),
            ]);

        return $this->formatCdrNo($year, $nextSequence);
    }

    private function previewNextCdrNo($date = null): string
    {
        $year = $this->cdrYear($date);
        $sequenceKey = $this->cdrSequenceKey($year);
        $initialLastNumber = $this->initialLastNumberForYear($year);

        if (!Schema::hasTable(self::CDR_SEQUENCE_TABLE)) {
            return $this->formatCdrNo($year, $initialLastNumber + 1);
        }

        $sequenceRow = DB::table(self::CDR_SEQUENCE_TABLE)
            ->where('document_key', $sequenceKey)
            ->first();

        $lastSequence = max((int) ($sequenceRow->last_number ?? 0), $initialLastNumber);

        return $this->formatCdrNo($year, $lastSequence + 1);
    }

    private function cdrYear($date = null): int
    {
        if ($date instanceof \DateTimeInterface) {
            return (int) $date->format('Y');
        }

        $value = trim((string) ($date ?? ''));

        if ($value === '') {
            return (int) now()->format('Y');
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return (int) $value;
        }

        try {
            return (int) Carbon::parse($value)->format('Y');
        } catch (\Throwable $e) {
            return (int) now()->format('Y');
        }
    }

    private function cdrSequenceKey(int $year): string
    {
        return self::CDR_SEQUENCE_PREFIX . $year;
    }

    private function initialLastNumberForYear(int $year): int
    {
        return $year === self::CDR_START_YEAR ? self::CDR_START_SEQUENCE - 1 : 0;
    }

    private function formatCdrNo(int $year, int $sequence): string
    {
        return str_pad((string) $sequence, self::CDR_PAD_LENGTH, '0', STR_PAD_LEFT) . '/' . $year;
    }

    public function getNextNumber(Request $request)
    {
        $yearInput = $request->input('year');
        $dateInput = $request->input('date', $yearInput);
        $nextNumber = $this->previewNextCdrNo($dateInput);

        return response()->json([
            'success' => true,
            'data' => [
                'next_cdr_no' => $nextNumber,
                'next_number' => $nextNumber,
                'year' => $this->cdrYear($dateInput),
            ],
        ]);
    }

    public function getList()
    {
        $Item = ControlledDocumentRequests::orderBy('id', 'desc')->get()->toArray();

        if (!empty($Item)) {
            foreach ($Item as $i => $v) {
                $Item[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========================================================
    // getPage (DataTable)
    // =========================================================
    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length  = $request->length ?? 10;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start ?? 0;

        $page = floor($start / $length) + 1;

        // ถ้าอยากกรองตามสถานะอนุมัติ
        $approvedStatus = $request->approved_by_status; // optional

        $col = [
            'id',
            'to',
            'from',
            'date',
            'cdr_no',
            'document_name',
            'categories',
            'request_for',
            'current_revision',
            'reason_description',
            'effective_date_purpose',
            'attach_document_note',
            'attachments',
            'requested_by',
            'requested_date',
            'review_comments',
            'reviewed_by',
            'reviewed_by_status',
            'reviewed_by_date',
            'approval_comments',
            'approved_by',
            'approved_by_status',
            'approved_by_date',
            'new_revision',
            'action_effective_date',
            'acknowledged_by',
            'acknowledged_by_status',
            'acknowledged_by_status_2',
            'acknowledged_by_date',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $orderby = [
            '',
            'cdr_no',
            'document_name',
            'categories',
            'request_for',
            'requested_by',
            'requested_date',
            'reviewed_by_status',
            'approved_by_status',
            'acknowledged_by_status',
            'acknowledged_by_status_2',
            'created_at',
        ];

        $D = ControlledDocumentRequests::select($col);

        // filter ตามสถานะการอนุมัติ (ถ้าส่งมา)
        if (!empty($approvedStatus)) {
            $D->where('approved_by_status', $approvedStatus);
        }

        // Search
        if (!empty($search['value'])) {
            $keyword = '%' . $search['value'] . '%';
            $D->where(function ($q) use ($keyword, $col) {
                foreach ($col as $c) {
                    $q->orWhere($c, 'like', $keyword);
                }
            });
        }

        // Order
        if (!empty($order)) {
            $idx = $order[0]['column'];
            $dir = $order[0]['dir'];
            if (isset($orderby[$idx]) && $orderby[$idx] !== '') {
                $D->orderBy($orderby[$idx], $dir);
            }
        } else {
            $D->orderBy('id', 'desc');
        }

        $data = $D->paginate($length, ['*'], 'page', $page);

        if ($data->isNotEmpty()) {
            $no = (($page - 1) * $length);
            foreach ($data as $i => $row) {
                $row->No = ++$no;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }
    // ============================
    // Store
    // ============================
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = new ControlledDocumentRequests();

            $Item->to = $request->to;
            $Item->from = $request->from;
            $Item->date = $request->date;

            $Item->cdr_no = $this->nextCdrNo($request->date);
            $Item->categories = $request->categories;
            $Item->request_for = $request->request_for;

            $Item->document_name = $request->document_name;
            $Item->current_revision = $request->current_revision;
            $Item->reason_description = $request->reason_description;
            $Item->effective_date_purpose = $request->effective_date_purpose;
            $Item->attach_document_note = $request->attach_document_note;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $Item->attachments = $this->encodeAttachments($normalizedAttachments);

            $Item->requested_by = $request->requested_by;
            $Item->requested_date = $request->requested_date;

            $Item->review_comments = $request->review_comments;
            $Item->reviewed_by = $request->reviewed_by;
            $Item->reviewed_by_status = $request->reviewed_by_status;
            $Item->reviewed_by_date = $request->reviewed_by_date;

            $Item->approval_comments = $request->approval_comments;
            $Item->approved_by = $request->approved_by;
            $Item->approved_by_status = $request->approved_by_status;
            $Item->approved_by_date = $request->approved_by_date;

            $Item->new_revision = $request->new_revision;
            $Item->action_effective_date = $request->action_effective_date;

            $Item->acknowledged_by = $request->acknowledged_by;
            $Item->acknowledged_by_status = $request->acknowledged_by_status;
            $Item->acknowledged_by_status_2 = $request->acknowledged_by_status_2;
            $Item->acknowledged_by_date = $request->acknowledged_by_date;

            $Item->create_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';

            $Item->save();
            $Item->refresh();
            $Item->attachments = $normalizedAttachments;

            $this->logDocumentCreateAudit($request, $Item);

            DB::commit();

            return $this->returnSuccess("บันทึกข้อมูลสำเร็จ", $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData("เกิดข้อผิดพลาด: " . $e->getMessage(), 500);
        }
    }

    // ============================
    // show
    // ============================
    public function show($id)
    {
        $Item = ControlledDocumentRequests::find($id);

        if (!$Item)
            return $this->returnErrorData("ไม่พบข้อมูล", 404);

        return $this->returnSuccess("เรียกดูข้อมูลสำเร็จ", $Item);
    }

    // ============================
    // update
    // ============================
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = ControlledDocumentRequests::find($id);
            if (!$Item)
                return $this->returnErrorData("ไม่พบข้อมูล", 404);

            $Item->fill($request->except(['login_by', 'attachments']));

            if ($request->has('attachments')) {
                $attachments = $request->input('attachments');
                $normalizedAttachments = $this->normalizeAttachments($attachments);
                $Item->attachments = $this->encodeAttachments($normalizedAttachments);
            }

            $Item->update_by = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $Item->save();

            if ($request->has('attachments')) {
                $Item->attachments = $normalizedAttachments;
            }

            DB::commit();
            return $this->returnUpdate("อัปเดตข้อมูลสำเร็จ", $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData("เกิดข้อผิดพลาด: " . $e->getMessage(), 500);
        }
    }

    // ============================
    // destroy
    // ============================
    public function destroy(Request $request, $id)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = ControlledDocumentRequests::find($id);
            if (!$Item)
                return $this->returnErrorData("ไม่พบข้อมูล", 404);

            $Item->delete();

            $this->Log($loginBy->employee_code ?? $loginBy->id ?? 'admin',
                "ลบข้อมูล CDR #$id",
                "delete");

            DB::commit();
            return $this->returnSuccess("ลบข้อมูลสำเร็จ", []);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData("เกิดข้อผิดพลาด: " . $e->getMessage(), 500);
        }
    }
}
