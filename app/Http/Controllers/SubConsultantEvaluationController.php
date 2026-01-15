<?php

namespace App\Http\Controllers;

use App\Models\SubConsultantEvaluation;
use App\Models\SubConsultantEvaluationItem;
use App\Models\SubConsultantEvaluationFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubConsultantEvaluationController extends Controller
{
    // ===================== getList =====================
    public function getList()
    {
        $Item = SubConsultantEvaluation::orderBy('id', 'desc')->get()->toArray();

        if (!empty($Item)) {
            for ($i = 0; $i < count($Item); $i++) {
                $Item[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // ===================== getPage =====================
    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length  = $request->length;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start;
        $page    = $length ? ($start / $length + 1) : 1;

        $subConsultantName = $request->sub_consultant_name;
        $projectName       = $request->project_name;

        $col = [
            'id',
            'to',
            'circ',
            'sub_consultant_name',
            'project_name',
            'project_no',
            'department_value_duration',
            'anti_corruption_is_violation',
            'cad_software_confirmed',
            'is_maintained',
            'is_removed',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        $orderby = [
            '',                     // index 0 = คอลัมน์ No (ไม่ใช้ sort)
            'sub_consultant_name',  // index 1
            'project_name',         // index 2
            'project_no',           // index 3
            'created_at',           // index 4
            'create_by',            // index 5
        ];

        $D = SubConsultantEvaluation::with(['files','items'])->select($col);

        if (!empty($subConsultantName)) {
            $D->where('sub_consultant_name', 'like', '%' . $subConsultantName . '%');
        }

        if (!empty($projectName)) {
            $D->where('project_name', 'like', '%' . $projectName . '%');
        }

        // 🛠 แก้ตรงนี้
        if (!empty($order)) {
            $index = $order[0]['column'];

            if (!empty($orderby[$index] ?? null)) {
                $D->orderBy($orderby[$index], $order[0]['dir']);
            } else {
                // ถ้า column map เป็น '' หรือไม่เจอ → fallback
                $D->orderBy('id', 'desc');
            }
        } else {
            $D->orderBy('id', 'desc');
        }

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


    // ===================== show =====================
    public function show($id)
    {
        $Item = SubConsultantEvaluation::with(['items','files'])->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        // ดึง items มาด้วย
        $items = SubConsultantEvaluationItem::where('sub_consultant_eva_id', $Item->id)
            ->orderBy('item_no', 'asc')
            ->get();

        $Item->items = $items;

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // ===================== store =====================
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // validate แบบง่าย ตามฟอร์ม (ต้องมี ชื่อ sub-consultant + project_name)
        if (!isset($request->sub_consultant_name)) {
            return $this->returnErrorData('กรุณาระบุ sub_consultant_name', 404);
        }
        if (!isset($request->project_name)) {
            return $this->returnErrorData('กรุณาระบุ project_name', 404);
        }

        DB::beginTransaction();

        try {
            $Item = new SubConsultantEvaluation();

            // ----- Evaluation Details -----
            $Item->to                       = $request->to ?? null;
            $Item->circ                     = $request->circ ?? null;
            $Item->sub_consultant_name      = $request->sub_consultant_name;
            $Item->project_name             = $request->project_name;
            $Item->project_no               = $request->project_no ?? null;
            $Item->department_value_duration = $request->department_value_duration ?? null;
            $Item->scope_of_services        = $request->scope_of_services ?? null;

            // ----- Anti-Corruption -----
            $Item->anti_corruption_is_violation = isset($request->anti_corruption_is_violation)
                ? (bool)$request->anti_corruption_is_violation
                : null;

            $Item->cad_software_confirmed = isset($request->cad_software_confirmed)
                ? (bool)$request->cad_software_confirmed
                : null;

            // ----- Decision -----
            $Item->is_maintained = isset($request->is_maintained)
                ? (bool)$request->is_maintained
                : null;

            $Item->is_removed = isset($request->is_removed)
                ? (bool)$request->is_removed
                : null;

            // ----- Evaluated / Approved / Acknowledged -----
            $Item->evaluated_by      = $request->evaluated_by ?? null;
            $Item->evaluated_by_date    = $request->evaluated_by_date ?? null;
            $Item->evaluated_by_status       = $request->evaluated_by_status ?? null;

            $Item->approved_by       = $request->approved_by ?? null;
            $Item->approved_date     = $request->approved_date ?? null;
            $Item->approved_by_status       = $request->approved_by_status ?? null;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status       = $request->acknowledged_by_status ?? null;

            // create_by
            $Item->create_by = $loginBy->id ?? 'admin';

            $Item->save();

            // ----- Items (Rating / Comment) -----
            if (is_array($request->items) && !empty($request->items)) {
                foreach ($request->items as $row) {
                    // ข้ามถ้าไม่มีชื่อ item
                    if (!isset($row['item_name'])) {
                        continue;
                    }

                    $ItemRow = new SubConsultantEvaluationItem();
                    $ItemRow->sub_consultant_eva_id = $Item->id;
                    $ItemRow->item_no   = $row['item_no'] ?? 0;
                    $ItemRow->item_name = $row['item_name'] ?? null;
                    $ItemRow->rating    = $row['rating'] ?? null;
                    $ItemRow->comment   = $row['comment'] ?? null;
                    $ItemRow->create_by = $loginBy->id ?? 'admin';
                    $ItemRow->save();
                }
            }

            if (is_array($request->input('files')) && !empty($request->input('files'))) {

                foreach ($request->input('files') as $file) {
                    if (!isset($file['path'])) {
                        continue;
                    }

                    $att = new SubConsultantEvaluationFiles();
                    $att->sub_consultant_eva_id = $Item->id;
                    $att->name = $file['name'] ?? null;
                    $att->path = $file['path'];
                    $att->create_by = $loginBy->id ?? 'admin';
                    $att->save();
                }
            }

            DB::commit();
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // ===================== update =====================
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        // validate เบื้องต้น
        if (!isset($request->sub_consultant_name)) {
            return $this->returnErrorData('กรุณาระบุ sub_consultant_name', 404);
        }
        if (!isset($request->project_name)) {
            return $this->returnErrorData('กรุณาระบุ project_name', 404);
        }

        DB::beginTransaction();

        try {
            $Item = SubConsultantEvaluation::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            // ----- Evaluation Details -----
            $Item->to                       = $request->to ?? null;
            $Item->circ                     = $request->circ ?? null;
            $Item->sub_consultant_name      = $request->sub_consultant_name;
            $Item->project_name             = $request->project_name;
            $Item->project_no               = $request->project_no ?? null;
            $Item->department_value_duration = $request->department_value_duration ?? null;
            $Item->scope_of_services        = $request->scope_of_services ?? null;

            // ----- Anti-Corruption -----
            $Item->anti_corruption_is_violation = isset($request->anti_corruption_is_violation)
                ? (bool)$request->anti_corruption_is_violation
                : null;

            $Item->cad_software_confirmed = isset($request->cad_software_confirmed)
                ? (bool)$request->cad_software_confirmed
                : null;

            // ----- Decision -----
            $Item->is_maintained = isset($request->is_maintained)
                ? (bool)$request->is_maintained
                : null;

            $Item->is_removed = isset($request->is_removed)
                ? (bool)$request->is_removed
                : null;

            // ----- Evaluated / Approved / Acknowledged -----
            $Item->evaluated_by      = $request->evaluated_by ?? null;
            $Item->evaluated_by_date    = $request->evaluated_by_date ?? null;
            $Item->evaluated_by_status       = $request->evaluated_by_status ?? null;

            $Item->approved_by       = $request->approved_by ?? null;
            $Item->approved_by_date     = $request->approved_by_date ?? null;
            $Item->approved_by_status       = $request->approved_by_status ?? null;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status       = $request->acknowledged_by_status ?? null;

            // update_by
            $Item->update_by = $loginBy->id ?? 'admin';

            $Item->save();

            // ----- อัปเดต Items -----
            if (is_array($request->items)) {
                // ลบของเก่า (soft delete ตาม FK cascade)
                SubConsultantEvaluationItem::where('sub_consultant_eva_id', $Item->id)->delete();

                foreach ($request->items as $row) {
                    if (!isset($row['item_name'])) {
                        continue;
                    }

                    $ItemRow = new SubConsultantEvaluationItem();
                    $ItemRow->sub_consultant_eva_id = $Item->id;
                    $ItemRow->item_no   = $row['item_no'] ?? 0;
                    $ItemRow->item_name = $row['item_name'] ?? null;
                    $ItemRow->rating    = $row['rating'] ?? null;
                    $ItemRow->comment   = $row['comment'] ?? null;
                    $ItemRow->create_by = $loginBy->id ?? 'admin';
                    $ItemRow->save();
                }
            }

            DB::commit();
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // ===================== destroy =====================
    public function destroy($id, Request $request)
    {
        $loginBy = $request->login_by;

        if (!isset($id)) {
            return $this->returnErrorData('ไม่พบข้อมูล id', 404);
        }

        DB::beginTransaction();

        try {
            $Item = SubConsultantEvaluation::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete(); // FK cascade จะลบ items / attachments ให้ (แบบ soft delete)

            // log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบข้อมูล sub_consultant_evaluations';
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
