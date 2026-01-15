<?php

namespace App\Http\Controllers;

use App\Models\GiftHospitalityOffering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GiftHospitalityOfferingController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = GiftHospitalityOffering::orderBy('id', 'desc')->get()->toArray();

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
        $length  = $request->length;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start;
        $page    = $start / $length + 1;

        $RequestType = $request->request_type;

        $col = [
            'id',
            'request_type',
            'description',
            'purpose',
            'value',
            'receiver_name_and_company',
            'proposed_date',
            'verified_by',
            'verified_by_date',
            'verified_by_status',
            'acknowledged_by',
            'acknowledged_by_date',
            'acknowledged_by_status',
            'approved_by',
            'approved_by_date',
            'approved_by_status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        $orderby = [
            '',
            'request_type',
            'value',
            'proposed_date',
            'receiver_name_and_company',
            'create_by',
        ];

        $D = GiftHospitalityOffering::select($col);

        if (!empty($RequestType)) {
            $D->where('request_type', $RequestType);
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
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    }

    // =========== show ===========
    public function show($id)
    {
        $Item = GiftHospitalityOffering::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // validate แบบ snake_case
        if (!isset($request->request_type)) {
            return $this->returnErrorData('กรุณาระบุ request_type', 404);
        }
        if (!isset($request->description)) {
            return $this->returnErrorData('กรุณาระบุ description', 404);
        }
        if (!isset($request->purpose)) {
            return $this->returnErrorData('กรุณาระบุ purpose', 404);
        }
        if (!isset($request->value)) {
            return $this->returnErrorData('กรุณาระบุ value', 404);
        }
        if (!isset($request->receiver_name_and_company)) {
            return $this->returnErrorData('กรุณาระบุ receiver_name_and_company', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุ proposed_date', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new GiftHospitalityOffering();
            $Item->request_type             = $request->request_type;
            $Item->description              = $request->description;
            $Item->purpose                  = $request->purpose;
            $Item->value                    = $request->value;

            $Item->receiver_name_and_company = $request->receiver_name_and_company;
            $Item->proposed_date             = $request->proposed_date;

            $Item->verified_by         = $request->verified_by ?? null;
            $Item->verified_by_date    = $request->verified_by_date ?? null;
            $Item->verified_by_status  = $request->verified_by_status ?? null;
            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date    = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status  = $request->acknowledged_by_status ?? null;
            $Item->approved_by         = $request->approved_by ?? null;
            $Item->approved_by_date    = $request->approved_by_date ?? null;
            $Item->approved_by_status  = $request->approved_by_status ?? null;

            $Item->create_by                = $loginBy->id ?? 'admin';

            $Item->save();

            DB::commit();
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $Item);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== update ===========
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        // validate แบบเดียวกับ store
        if (!isset($request->request_type)) {
            return $this->returnErrorData('กรุณาระบุ request_type', 404);
        }
        if (!isset($request->description)) {
            return $this->returnErrorData('กรุณาระบุ description', 404);
        }
        if (!isset($request->purpose)) {
            return $this->returnErrorData('กรุณาระบุ purpose', 404);
        }
        if (!isset($request->value)) {
            return $this->returnErrorData('กรุณาระบุ value', 404);
        }
        if (!isset($request->receiver_name_and_company)) {
            return $this->returnErrorData('กรุณาระบุ receiver_name_and_company', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุ proposed_date', 404);
        }


        DB::beginTransaction();

        try {

            $Item = GiftHospitalityOffering::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            $Item->request_type             = $request->request_type;
            $Item->description              = $request->description;
            $Item->purpose                  = $request->purpose;
            $Item->value                    = $request->value;

            $Item->receiver_name_and_company = $request->receiver_name_and_company;
            $Item->proposed_date             = $request->proposed_date;

            $Item->verified_by         = $request->verified_by ?? null;
            $Item->verified_by_date    = $request->verified_by_date ?? null;
            $Item->verified_by_status  = $request->verified_by_status ?? null;
            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date    = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status  = $request->acknowledged_by_status ?? null;
            $Item->approved_by         = $request->approved_by ?? null;
            $Item->approved_by_date    = $request->approved_by_date ?? null;
            $Item->approved_by_status  = $request->approved_by_status ?? null;

            $Item->update_by                = $loginBy->id ?? 'admin';

            $Item->save();

            DB::commit();
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $Item);

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

            $Item = GiftHospitalityOffering::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete();

            // log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบข้อมูล gift_hospitality_offerings';
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
