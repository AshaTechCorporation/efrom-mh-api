<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = Car::orderBy('id', 'desc')->get()->toArray();

        if (!empty($Item)) {
            for ($i = 0; $i < count($Item); $i++) {
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

        $Severity = $request->severity;

        $col = array(
            'id',
            'department',
            'project_name',
            'ref_no',
            'project_no',
            'to',
            'date',
            'car_issued_by',
            'severity',
            'non_conformity_description',
            'responsible_person_id',
            'imr_id',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        );

        $orderby = array(
            '',
            'department',
            'project_name',
            'ref_no',
            'date',
            'severity',
            'create_by',
        );

        $D = Car::select($col);

        if (isset($Severity)) {
            $D->where('severity', $Severity);
        }

        if ($orderby[$order[0]['column']] ?? false) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
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
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    }

    // =========== show ===========
    public function show($id)
    {
        $Item = Car::find($id);

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
        if (!isset($request->department)) {
            return $this->returnErrorData('กรุณาระบุ department', 404);
        }
        if (!isset($request->project_name)) {
            return $this->returnErrorData('กรุณาระบุ project_name', 404);
        }
        if (!isset($request->ref_no)) {
            return $this->returnErrorData('กรุณาระบุ ref_no', 404);
        }
        if (!isset($request->date)) {
            return $this->returnErrorData('กรุณาระบุ date', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new Car();
            $Item->department                   = $request->department;
            $Item->project_name                 = $request->project_name;
            $Item->ref_no                       = $request->ref_no;
            $Item->project_no                   = $request->project_no ?? null;
            $Item->to                           = $request->to ?? null;
            $Item->date                         = $request->date;

            $Item->car_issued_by                = $request->car_issued_by ?? null;

            $Item->sources = is_array($request->sources)
                ? json_encode($request->sources, JSON_UNESCAPED_UNICODE)
                : $request->sources;

            $Item->other_source_description     = $request->other_source_description ?? null;
            $Item->severity                     = $request->severity ?? null;

            $Item->non_conformity_types = is_array($request->non_conformity_types)
                ? json_encode($request->non_conformity_types, JSON_UNESCAPED_UNICODE)
                : $request->non_conformity_types;

            $Item->non_conformity_description   = $request->non_conformity_description ?? null;
            $Item->responsible_person_id        = $request->responsible_person_id ?? null;
            $Item->imr_id                       = $request->imr_id ?? null;

            $Item->create_by                    = $loginBy->id ?? 'admin';

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

        // validate แบบ snake_case
        if (!isset($request->department)) {
            return $this->returnErrorData('กรุณาระบุ department', 404);
        }
        if (!isset($request->project_name)) {
            return $this->returnErrorData('กรุณาระบุ project_name', 404);
        }
        if (!isset($request->ref_no)) {
            return $this->returnErrorData('กรุณาระบุ ref_no', 404);
        }
        if (!isset($request->date)) {
            return $this->returnErrorData('กรุณาระบุ date', 404);
        }


        DB::beginTransaction();

        try {

            $Item = Car::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            $Item->department                   = $request->department;
            $Item->project_name                 = $request->project_name;
            $Item->ref_no                       = $request->ref_no;
            $Item->project_no                   = $request->project_no ?? null;
            $Item->to                           = $request->to ?? null;
            $Item->date                         = $request->date;

            $Item->car_issued_by                = $request->car_issued_by ?? null;

            $Item->sources = is_array($request->sources)
                ? json_encode($request->sources, JSON_UNESCAPED_UNICODE)
                : $request->sources;

            $Item->other_source_description     = $request->other_source_description ?? null;
            $Item->severity                     = $request->severity ?? null;

            $Item->non_conformity_types = is_array($request->non_conformity_types)
                ? json_encode($request->non_conformity_types, JSON_UNESCAPED_UNICODE)
                : $request->non_conformity_types;

            $Item->non_conformity_description   = $request->non_conformity_description ?? null;
            $Item->responsible_person_id        = $request->responsible_person_id ?? null;
            $Item->imr_id                       = $request->imr_id ?? null;

            $Item->update_by                    = $loginBy->id ?? 'admin';

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

            $Item = Car::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete();

            // log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบข้อมูล cars';
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
