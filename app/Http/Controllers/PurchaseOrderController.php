<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;


class PurchaseOrderController extends Controller
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
        $Item = PurchaseOrder::orderBy('id', 'desc')->get()->toArray();

        if (!empty($Item)) {
            for ($i = 0; $i < count($Item); $i++) {
                $Item[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== getPage (DataTables style) ===========
    public function getPage(Request $request)
    {
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
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    }

    // =========== show ===========
    public function show($id)
    {
        $Item = PurchaseOrder::with('items')->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
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
            $Item->requisition_date = $request->requisition_date;
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
            $Item->purchase_request_by_date = $request->purchase_request_by_date ?? null;
            $Item->purchase_request_by_status = $request->purchase_request_by_status ?? null;
            $Item->verified_by           = $request->verified_by ?? null;
            $Item->verified_by_date = $request->verified_by_date ?? null;
            $Item->verified_by_status = $request->verified_by_status ?? null;
            $Item->approved_by           = $request->approved_by ?? null;
            $Item->approved_by_date = $request->approved_by_date ?? null;
            $Item->approved_by_status = $request->approved_by_status ?? null;

            // Checklist
            $Item->delivery_on_time          = $request->delivery_on_time ?? null;
            $Item->meet_quality_requirement  = $request->meet_quality_requirement ?? null;
            $Item->meet_equipment_guidelines = $request->meet_equipment_guidelines ?? null;

            // Comments & Signatures
            $Item->comments          = $request->comments ?? null;
            $Item->signed_by         = $request->signed_by ?? null;
            $Item->signed_by_date = $request->signed_by_date ?? null;
            $Item->signed_by_status = $request->signed_by_status ?? null;
            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? null;

            $attachments = $request->input('attachments');
            $normalizedAttachments = $this->normalizeAttachments($attachments);
            $Item->attachments = $this->encodeAttachments($normalizedAttachments);

            $Item->create_by = $loginBy->id ?? 'admin';
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
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $Item->load('items'));

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== update ===========
    public function update(Request $request, $id)
    {
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


            // Header
            $Item->to       = $request->to;
            $Item->company  = $request->company;
            $Item->fax      = $request->fax ?? null;
            $Item->from     = $request->from;
            $Item->cc       = $request->cc ?? null;

            // PO Info
            $Item->po_no            = $request->po_no ?? null;
            $Item->po_date          = $request->po_date;
            $Item->requisition_date = $request->requisition_date;
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
            $Item->purchase_request_by_date = $request->purchase_request_by_date ?? null;
            $Item->purchase_request_by_status = $request->purchase_request_by_status ?? null;
            $Item->verified_by           = $request->verified_by ?? null;
            $Item->verified_by_date = $request->verified_by_date ?? null;
            $Item->verified_by_status = $request->verified_by_status ?? null;
            $Item->approved_by           = $request->approved_by ?? null;
            $Item->approved_by_date = $request->approved_by_date ?? null;
            $Item->approved_by_status = $request->approved_by_status ?? null;

            // Checklist
            $Item->delivery_on_time          = $request->delivery_on_time ?? null;
            $Item->meet_quality_requirement  = $request->meet_quality_requirement ?? null;
            $Item->meet_equipment_guidelines = $request->meet_equipment_guidelines ?? null;

            // Comments & Signatures
            $Item->comments          = $request->comments ?? null;
            $Item->signed_by         = $request->signed_by ?? null;
            $Item->signed_by_date = $request->signed_by_date ?? null;
            $Item->signed_by_status = $request->signed_by_status ?? null;
            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? null;

            if ($request->has('attachments')) {
                $attachments = $request->input('attachments');
                $normalizedAttachments = $this->normalizeAttachments($attachments);
                $Item->attachments = $this->encodeAttachments($normalizedAttachments);
            }

            $Item->update_by = $loginBy->id ?? 'admin';
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
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $Item->load('items'));

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== destroy ===========
    public function destroy($id, Request $request)
    {
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

            $Item->delete();

            // log
            $userId      = $loginBy->id ?? 'admin';
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
