<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ControlledDocumentRequests;

class ControlledDocumentRequestsController extends Controller
{
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
            'cdr_no',
            'document_name',
            'categories',
            'request_for',
            'requested_by',
            'requested_date',
            'reviewed_by_status',
            'approved_by_status',
            'acknowledged_by_status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
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

        // validate เบื้องต้น
        if (!isset($request->cdr_no))
            return $this->returnErrorData("กรุณาระบุเลข CDR (cdr_no)", 400);

        DB::beginTransaction();

        try {
            $Item = new ControlledDocumentRequests();

            $Item->to = $request->to;
            $Item->from = $request->from;
            $Item->date = $request->date;

            $Item->cdr_no = $request->cdr_no;
            $Item->categories = $request->categories;
            $Item->request_for = $request->request_for;

            $Item->document_name = $request->document_name;
            $Item->current_revision = $request->current_revision;
            $Item->reason_description = $request->reason_description;
            $Item->effective_date_purpose = $request->effective_date_purpose;
            $Item->attach_document_note = $request->attach_document_note;

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
            $Item->acknowledged_by_date = $request->acknowledged_by_date;

            $Item->create_by = $loginBy->id ?? 'admin';

            $Item->save();
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

            foreach ($request->all() as $key => $val) {
                if ($key === 'login_by') continue;
                if ($Item->isFillable($key) || isset($Item->$key)) {
                    $Item->$key = $val;
                }
            }

            $Item->update_by = $loginBy->id ?? 'admin';
            $Item->save();

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

            $this->Log($loginBy->id ?? 'admin',
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
