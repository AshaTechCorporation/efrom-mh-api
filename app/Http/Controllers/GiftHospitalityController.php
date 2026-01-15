<?php

namespace App\Http\Controllers;

use App\Models\GiftHospitality;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GiftHospitalityController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = GiftHospitality::orderBy('id', 'desc')->get()->toArray();

        if (!empty($Item)) {
            foreach ($Item as $i => $row) {
                $Item[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== getPage ===========
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
            'company_of_giver',
            'proposed_date',
            'mtl_receiving_staff_by',
            'mtl_receiving_staff_by_date',
            'mtl_receiving_staff_by_status',
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
            'company_of_giver',
            'create_by',
        ];

        $D = GiftHospitality::select($col);

        if (!empty($RequestType)) {
            $D->where('request_type', $RequestType);
        }

        // order
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
            foreach ($d as $i => $row) {
                $No++;
                $d[$i]->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    }

    // =========== show ===========
    public function show($id)
    {
        $Item = GiftHospitality::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // Validate snake_case
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
        if (!isset($request->company_of_giver)) {
            return $this->returnErrorData('กรุณาระบุ company_of_giver', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุ proposed_date', 404);
        }


        DB::beginTransaction();

        try {

            $Item = new GiftHospitality();
            $Item->request_type           = $request->request_type;
            $Item->description            = $request->description;
            $Item->purpose                = $request->purpose;
            $Item->value                  = $request->value;

            $Item->company_of_giver       = $request->company_of_giver;
            $Item->proposed_date          = $request->proposed_date;
            $Item->mtl_receiving_staff_by    = $request->mtl_receiving_staff_by ?? null;
            $Item->mtl_receiving_staff_by_date    = $request->mtl_receiving_staff_by_date ?? null;
            $Item->mtl_receiving_staff_by_status  = $request->mtl_receiving_staff_by_status ?? null;
            $Item->verified_by         = $request->verified_by ?? null;
            $Item->verified_by_date    = $request->verified_by_date ?? null;
            $Item->verified_by_status  = $request->verified_by_status ?? null;
            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date    = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status  = $request->acknowledged_by_status ?? null;
            $Item->approved_by         = $request->approved_by ?? null;
            $Item->approved_by_date    = $request->approved_by_date ?? null;
            $Item->approved_by_status  = $request->approved_by_status ?? null;

            $Item->create_by              = $loginBy->id ?? 'admin';

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

        // Validate snake_case
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
        if (!isset($request->company_of_giver)) {
            return $this->returnErrorData('กรุณาระบุ company_of_giver', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุ proposed_date', 404);
        }


        DB::beginTransaction();

        try {

            $Item = GiftHospitality::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            $Item->request_type           = $request->request_type;
            $Item->description            = $request->description;
            $Item->purpose                = $request->purpose;
            $Item->value                  = $request->value;

            $Item->company_of_giver       = $request->company_of_giver;
            $Item->proposed_date          = $request->proposed_date;
            $Item->mtl_receiving_staff_by    = $request->mtl_receiving_staff_by ?? null;
            $Item->mtl_receiving_staff_by_date    = $request->mtl_receiving_staff_by_date ?? null;
            $Item->mtl_receiving_staff_by_status  = $request->mtl_receiving_staff_by_status ?? null;
            $Item->verified_by         = $request->verified_by ?? null;
            $Item->verified_by_date    = $request->verified_by_date ?? null;
            $Item->verified_by_status  = $request->verified_by_status ?? null;
            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date    = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status  = $request->acknowledged_by_status ?? null;
            $Item->approved_by         = $request->approved_by ?? null;
            $Item->approved_by_date    = $request->approved_by_date ?? null;
            $Item->approved_by_status  = $request->approved_by_status ?? null;

            $Item->update_by              = $loginBy->id ?? 'admin';

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

            $Item = GiftHospitality::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete();

            // Log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบข้อมูล gift_hospitalities';
            $description = "ผู้ใช้งาน {$userId} ได้ทำการ {$type} #{$Item->id}";
            $this->Log($userId, $description, $type);

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }
}
