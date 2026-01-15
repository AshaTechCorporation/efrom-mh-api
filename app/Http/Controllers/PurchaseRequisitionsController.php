<?php
namespace App\Http\Controllers;

use App\Models\PurchaseRequisitions;
use App\Models\PurchaseRequisitionItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionsController extends Controller
{
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

        $status  = $request->approved_by_status; // ตัวกรอง optional

        $col = [
            'id',
            'to',
            'date',
            'deadline',
            'recommended_by',
            'received_from',
            'requested_by',
            'requested_by_status',
            'approved_by',
            'approved_by_status',
            'verified_by_is_status',
            'verified_by_status',
            'acknowledged_by_status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
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

        $data = $D->paginate($length, ['*'], 'page', $page);

        if ($data->isNotEmpty()) {
            $no = (($page - 1) * $length);
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

        if (empty($request->to))   return $this->returnErrorData('กรุณาระบุ to', 404);
        if (empty($request->date)) return $this->returnErrorData('กรุณาระบุ date', 404);

        $items = $request->items ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
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

            $pr->requested_by            = $request->requested_by;
            $pr->requested_by_status     = $request->requested_by_status;
            $pr->requested_date          = $request->requested_date;

            $pr->verified_by_is          = $request->verified_by_is;
            $pr->verified_by_is_status   = $request->verified_by_is_status;
            $pr->verified_is_date        = $request->verified_is_date;

            $pr->verified_by             = $request->verified_by;
            $pr->verified_by_status      = $request->verified_by_status;
            $pr->verified_date           = $request->verified_date;

            $pr->approved_by             = $request->approved_by;
            $pr->approved_by_status      = $request->approved_by_status;
            $pr->approved_date           = $request->approved_date;

            $pr->acknowledged_by         = $request->acknowledged_by;
            $pr->acknowledged_by_status  = $request->acknowledged_by_status;
            $pr->acknowledged_date       = $request->acknowledged_date;

            $pr->need_asset_code_registration = $request->need_asset_code_registration;
            $pr->action_by_admin              = $request->action_by_admin;
            $pr->action_by_admin_date         = $request->action_by_admin_date;

            $pr->create_by = $loginBy->id ?? 'admin';
            $pr->save();

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
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $pr->load('items'));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // ================= update =================
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $pr = PurchaseRequisitions::with('items')->find($id);
            if (!$pr) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            // header เหมือน store (เช็ค required ตามที่ต้องการเองได้)
            $pr->to                      = $request->to ?? $pr->to;
            $pr->date                    = $request->date ?? $pr->date;
            $pr->deadline                = $request->deadline;
            $pr->recommended_by          = $request->recommended_by;
            $pr->received_from           = $request->received_from;
            $pr->reasons_for_purchase    = $request->reasons_for_purchase;
            $pr->other_conditions        = $request->other_conditions;
            $pr->quotation_attached      = $request->quotation_attached;

            $pr->requested_by            = $request->requested_by;
            $pr->requested_by_status     = $request->requested_by_status;
            $pr->requested_date          = $request->requested_date;

            $pr->verified_by_is          = $request->verified_by_is;
            $pr->verified_by_is_status   = $request->verified_by_is_status;
            $pr->verified_is_date        = $request->verified_is_date;

            $pr->verified_by             = $request->verified_by;
            $pr->verified_by_status      = $request->verified_by_status;
            $pr->verified_date           = $request->verified_date;

            $pr->approved_by             = $request->approved_by;
            $pr->approved_by_status      = $request->approved_by_status;
            $pr->approved_date           = $request->approved_date;

            $pr->acknowledged_by         = $request->acknowledged_by;
            $pr->acknowledged_by_status  = $request->acknowledged_by_status;
            $pr->acknowledged_date       = $request->acknowledged_date;

            $pr->need_asset_code_registration = $request->need_asset_code_registration;
            $pr->action_by_admin              = $request->action_by_admin;
            $pr->action_by_admin_date         = $request->action_by_admin_date;

            $pr->update_by = $loginBy->id ?? 'admin';
            $pr->save();

            // ลบ items เดิม แล้วสร้างใหม่จาก payload (ง่ายสุด)
            if ($request->has('items')) {
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
                    $item->create_by   = $loginBy->id ?? 'admin';
                    $item->save();
                }
            }

            DB::commit();
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $pr->load('items'));

        } catch (\Throwable $e) {
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
                $loginBy->id ?? 'admin',
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
