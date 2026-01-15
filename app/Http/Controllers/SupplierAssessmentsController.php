<?php

namespace App\Http\Controllers;

use App\Models\SupplierAssessments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupplierAssessmentsController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = SupplierAssessments::orderBy('id', 'desc')->get()->toArray();

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

        $Recommendation = $request->recommendation;
        $ApprovedList   = $request->approved_to_supplier_list;

        $col = [
            'id',
            'items_supplied',
            'company_name',
            'experience_score',
            'staff_score',
            'product_compliance_score',
            'total_score',
            'recommendation',
            'approved_to_supplier_list',
            'assessed_by',
            'assessed_by_date',
            'assessed_by_status',
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
            'items_supplied',
            'company_name',
            'total_score',
            'recommendation',
            'approved_to_supplier_list',
            'assessed_date',
            'approved_date',
            'create_by',
        ];

        $D = SupplierAssessments::select($col);

        if (!empty($Recommendation)) {
            $D->where('recommendation', $Recommendation);
        }

        if ($ApprovedList !== null && $ApprovedList !== '') {
            $D->where('approved_to_supplier_list', $ApprovedList);
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
        $Item = SupplierAssessments::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // validate แบบ snake_case ที่จำเป็น
        if (!isset($request->items_supplied)) {
            return $this->returnErrorData('กรุณาระบุ items_supplied', 404);
        }
        if (!isset($request->company_name)) {
            return $this->returnErrorData('กรุณาระบุ company_name', 404);
        }
        if (!isset($request->total_score)) {
            return $this->returnErrorData('กรุณาระบุ total_score', 404);
        }
        if (!isset($request->recommendation)) {
            return $this->returnErrorData('กรุณาระบุ recommendation', 404);
        }

        // แปลงวันที่ตามรูปแบบเดิม d-m-Y → Y-m-d
        $assessed_by_date     = $request->assessed_by_date;
        $approved_by_date     = $request->approved_by_date;
        $acknowledged_by_date = $request->acknowledged_by_date;

        try {
            if (!empty($assessed_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $assessed_by_date)) {
                $assessed_by_date = Carbon::createFromFormat('d-m-Y', $assessed_by_date)->format('Y-m-d');
            }
            if (!empty($approved_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $approved_by_date)) {
                $approved_by_date = Carbon::createFromFormat('d-m-Y', $approved_by_date)->format('Y-m-d');
            }
            if (!empty($acknowledged_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $acknowledged_by_date)) {
                $acknowledged_by_date = Carbon::createFromFormat('d-m-Y', $acknowledged_by_date)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // ถ้าแปลงไม่ได้ให้ใช้ค่าที่รับมา
        }

        DB::beginTransaction();

        try {

            $Item = new SupplierAssessments();
            // Assessment Details
            $Item->items_supplied = $request->items_supplied ?? null;
            $Item->company_name   = $request->company_name ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile            = !empty($request->info_company_profile) ? 1 : 0;
            $Item->info_project_reference          = !empty($request->info_project_reference) ? 1 : 0;
            $Item->info_site_visit                 = !empty($request->info_site_visit) ? 1 : 0;
            $Item->info_previous_assessment_record = !empty($request->info_previous_assessment_record) ? 1 : 0;
            $Item->info_previous_evaluation_record = !empty($request->info_previous_evaluation_record) ? 1 : 0;
            $Item->info_iso_certificates           = !empty($request->info_iso_certificates) ? 1 : 0;

            // Assessment Areas score
            $Item->experience_score         = $request->experience_score ?? 0;
            $Item->staff_score              = $request->staff_score ?? 0;
            $Item->product_compliance_score = $request->product_compliance_score ?? 0;
            $Item->total_score              = $request->total_score ?? 0;

            // References
            $Item->reference_a_name    = $request->reference_a_name ?? null;
            $Item->reference_a_opinion = $request->reference_a_opinion ?? null;
            $Item->reference_b_name    = $request->reference_b_name ?? null;
            $Item->reference_b_opinion = $request->reference_b_opinion ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null;
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Assessed & Approval workflow
            $Item->assessed_by  = $request->assessed_by ?? null;
            $Item->assessed_by_date = $assessed_by_date;

            $Item->approved_to_supplier_list = !empty($request->approved_to_supplier_list) ? 1 : 0;
            $Item->remark                    = $request->remark ?? null;

            $Item->approved_by   = $request->approved_by ?? null;
            $Item->approved_by_date = $approved_by_date;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $acknowledged_by_date;

            $Item->create_by = $loginBy->id ?? 'admin';

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

        // validate เหมือน store
        if (!isset($request->items_supplied)) {
            return $this->returnErrorData('กรุณาระบุ items_supplied', 404);
        }
        if (!isset($request->company_name)) {
            return $this->returnErrorData('กรุณาระบุ company_name', 404);
        }
        if (!isset($request->total_score)) {
            return $this->returnErrorData('กรุณาระบุ total_score', 404);
        }
        if (!isset($request->recommendation)) {
            return $this->returnErrorData('กรุณาระบุ recommendation', 404);
        }

        // แปลงวันที่รูปแบบเดิม
         $assessed_by_date     = $request->assessed_by_date;
        $approved_by_date     = $request->approved_by_date;
        $acknowledged_by_date = $request->acknowledged_by_date;

        try {
            if (!empty($assessed_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $assessed_by_date)) {
                $assessed_by_date = Carbon::createFromFormat('d-m-Y', $assessed_by_date)->format('Y-m-d');
            }
            if (!empty($approved_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $approved_by_date)) {
                $approved_by_date = Carbon::createFromFormat('d-m-Y', $approved_by_date)->format('Y-m-d');
            }
            if (!empty($acknowledged_by_date) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $acknowledged_by_date)) {
                $acknowledged_by_date = Carbon::createFromFormat('d-m-Y', $acknowledged_by_date)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // ถ้าแปลงไม่ได้ให้ใช้ค่าที่รับมา
        }

        DB::beginTransaction();

        try {

            $Item = SupplierAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            // Assessment Details
            $Item->items_supplied = $request->items_supplied ?? null;
            $Item->company_name   = $request->company_name ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile            = !empty($request->info_company_profile) ? 1 : 0;
            $Item->info_project_reference          = !empty($request->info_project_reference) ? 1 : 0;
            $Item->info_site_visit                 = !empty($request->info_site_visit) ? 1 : 0;
            $Item->info_previous_assessment_record = !empty($request->info_previous_assessment_record) ? 1 : 0;
            $Item->info_previous_evaluation_record = !empty($request->info_previous_evaluation_record) ? 1 : 0;
            $Item->info_iso_certificates           = !empty($request->info_iso_certificates) ? 1 : 0;

            // Assessment Areas score
            $Item->experience_score         = $request->experience_score ?? 0;
            $Item->staff_score              = $request->staff_score ?? 0;
            $Item->product_compliance_score = $request->product_compliance_score ?? 0;
            $Item->total_score              = $request->total_score ?? 0;

            // References
            $Item->reference_a_name    = $request->reference_a_name ?? null;
            $Item->reference_a_opinion = $request->reference_a_opinion ?? null;
            $Item->reference_b_name    = $request->reference_b_name ?? null;
            $Item->reference_b_opinion = $request->reference_b_opinion ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null;
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Assessed & Approval workflow
            $Item->assessed_by  = $request->assessed_by ?? null;
            $Item->assessed_by_date = $assessed_by_date;

            $Item->approved_to_supplier_list = !empty($request->approved_to_supplier_list) ? 1 : 0;
            $Item->remark                    = $request->remark ?? null;

            $Item->approved_by   = $request->approved_by ?? null;
            $Item->approved_by_date = $approved_by_date;

            $Item->acknowledged_by   = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date = $acknowledged_by_date;

            $Item->update_by = $loginBy->id ?? 'admin';

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

            $Item = SupplierAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete();

            // log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบ Supplier Assessment';
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
