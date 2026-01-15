<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ProjectQualityAssurancePlan;

class ProjectQualityAssurancePlanController extends Controller
{
    // =========================================================
    // getList
    // =========================================================
    public function getList()
    {
        $Item = ProjectQualityAssurancePlan::orderBy('id', 'desc')->get()->toArray();

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

        if (!$length || (int)$length <= 0) $length = 10;
        $page = floor($start / $length) + 1;

        $col = [
            'id',
            'revision',
            'date',
            'prepared_by_tl',
            'approved_by_di',
            'acknowledged_by_vve',
            'project_name',
            'project_no',
            'create_by',
            'update_by',
            'created_at',
            'updated_at'
        ];

        $orderby = [
            '',
            'revision',
            'date',
            'prepared_by_tl',
            'approved_by_di',
            'acknowledged_by_vve',
            'project_name',
            'project_no',
            'created_at'
        ];

        $D = ProjectQualityAssurancePlan::select($col);

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

    // =========================================================
    // show
    // =========================================================
    public function show($id)
    {
        $Item = ProjectQualityAssurancePlan::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบข้อมูลที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========================================================
    // store
    // =========================================================
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // Required ตามฟอร์ม + migration
        if (!isset($request->revision))               return $this->returnErrorData('กรุณาระบุ revision', 404);
        if (!isset($request->date))                   return $this->returnErrorData('กรุณาระบุ date', 404);
        if (!isset($request->prepared_by_tl))         return $this->returnErrorData('กรุณาระบุ prepared_by_tl', 404);
        if (!isset($request->approved_by_di))         return $this->returnErrorData('กรุณาระบุ approved_by_di', 404);
        if (!isset($request->acknowledged_by_vve))    return $this->returnErrorData('กรุณาระบุ acknowledged_by_vve', 404);
        if (!isset($request->project_name))           return $this->returnErrorData('กรุณาระบุ project_name', 404);
        if (!isset($request->project_no))             return $this->returnErrorData('กรุณาระบุ project_no', 404);

        DB::beginTransaction();

        try {
            $Item = new ProjectQualityAssurancePlan();

            // ===== Header Information =====
            $Item->revision               = $request->revision;
            $Item->date                   = $this->convertDMY($request->date);
            $Item->prepared_by_tl         = $request->prepared_by_tl;
            $Item->approved_by_di         = $request->approved_by_di;
            $Item->acknowledged_by_vve    = $request->acknowledged_by_vve;

            // ===== A. Project Details =====
            $Item->project_name           = $request->project_name;
            $Item->project_no             = $request->project_no;

            // ===== B. Scope of Services =====
            $Item->scope_cs               = $request->scope_cs;
            $Item->scope_me               = $request->scope_me;
            $Item->scope_leed_esd         = $request->scope_leed_esd;
            $Item->scope_facade           = $request->scope_facade;
            $Item->scope_lighting         = $request->scope_lighting;
            $Item->scope_pm               = $request->scope_pm;
            $Item->scope_cm               = $request->scope_cm;
            $Item->scope_transport        = $request->scope_transport;
            $Item->scope_geotechnical     = $request->scope_geotechnical;
            $Item->scope_qs               = $request->scope_qs;
            $Item->scope_engineering_audit= $request->scope_engineering_audit;
            $Item->scope_others_flag      = $request->scope_others_flag;
            $Item->scope_others_text      = $request->scope_others_text;

            // ===== C. Project Team & Coordinator =====
            // Project Team
            $Item->team_di                = $request->team_di;
            $Item->team_tl                = $request->team_tl;
            $Item->team_pm                = $request->team_pm;
            $Item->team_cm                = $request->team_cm;
            $Item->team_re                = $request->team_re;

            // Project Coordinator
            $Item->coord_cs               = $request->coord_cs;
            $Item->coord_facade           = $request->coord_facade;
            $Item->coord_others           = $request->coord_others;
            $Item->coord_me               = $request->coord_me;
            $Item->coord_lighting         = $request->coord_lighting;
            $Item->coord_leed_esd         = $request->coord_leed_esd;
            $Item->coord_transport        = $request->coord_transport;

            // ===== D. VVE / Reviewer =====
            $Item->reviewer_cs            = $request->reviewer_cs;
            $Item->reviewer_mvac          = $request->reviewer_mvac;
            $Item->reviewer_facade        = $request->reviewer_facade;
            $Item->reviewer_others        = $request->reviewer_others;
            $Item->reviewer_geotechnical  = $request->reviewer_geotechnical;
            $Item->reviewer_electrical    = $request->reviewer_electrical;
            $Item->reviewer_lighting      = $request->reviewer_lighting;
            $Item->reviewer_leed_esd      = $request->reviewer_leed_esd;
            $Item->reviewer_sn_fp         = $request->reviewer_sn_fp;
            $Item->reviewer_transport     = $request->reviewer_transport;

            // ===== E. Design Review / Verification / Validation Schedule =====
            $Item->dcr_review                         = $request->dcr_review;
            $Item->dcr_verification                   = $request->dcr_verification;
            $Item->dcr_validation                     = $request->dcr_validation;

            $Item->peer_review_review                 = $request->peer_review_review;
            $Item->peer_review_verification           = $request->peer_review_verification;
            $Item->peer_review_validation             = $request->peer_review_validation;

            $Item->submission_review                  = $request->submission_review;
            $Item->submission_verification            = $request->submission_verification;
            $Item->submission_validation              = $request->submission_validation;

            $Item->tender_review                      = $request->tender_review;
            $Item->tender_verification                = $request->tender_verification;
            $Item->tender_validation                  = $request->tender_validation;

            $Item->construction_review                = $request->construction_review;
            $Item->construction_verification          = $request->construction_verification;
            $Item->construction_validation            = $request->construction_validation;

            $Item->final_design_transport_review      = $request->final_design_transport_review;
            $Item->final_design_transport_verification= $request->final_design_transport_verification;
            $Item->final_design_transport_validation  = $request->final_design_transport_validation;

            $Item->engineering_audit_review           = $request->engineering_audit_review;
            $Item->engineering_audit_verification     = $request->engineering_audit_verification;
            $Item->engineering_audit_validation       = $request->engineering_audit_validation;

            $Item->validation_before_docs_issued      = $request->validation_before_docs_issued;
            $Item->validation_within_14days_after_docs= $request->validation_within_14days_after_docs;

            // Standard fields
            $Item->create_by = $loginBy->id ?? 'admin';

            $Item->save();

            DB::commit();
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========================================================
    // update
    // =========================================================
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {
            $Item = ProjectQualityAssurancePlan::find($id);
            if (!$Item) return $this->returnErrorData('ไม่พบข้อมูล', 404);

            // Header
            $Item->revision               = $request->revision;
            $Item->date                   = $this->convertDMY($request->date);
            $Item->prepared_by_tl         = $request->prepared_by_tl;
            $Item->approved_by_di         = $request->approved_by_di;
            $Item->acknowledged_by_vve    = $request->acknowledged_by_vve;

            // Project Details
            $Item->project_name           = $request->project_name;
            $Item->project_no             = $request->project_no;

            // Scope
            $Item->scope_cs               = $request->scope_cs;
            $Item->scope_me               = $request->scope_me;
            $Item->scope_leed_esd         = $request->scope_leed_esd;
            $Item->scope_facade           = $request->scope_facade;
            $Item->scope_lighting         = $request->scope_lighting;
            $Item->scope_pm               = $request->scope_pm;
            $Item->scope_cm               = $request->scope_cm;
            $Item->scope_transport        = $request->scope_transport;
            $Item->scope_geotechnical     = $request->scope_geotechnical;
            $Item->scope_qs               = $request->scope_qs;
            $Item->scope_engineering_audit= $request->scope_engineering_audit;
            $Item->scope_others_flag      = $request->scope_others_flag;
            $Item->scope_others_text      = $request->scope_others_text;

            // Team
            $Item->team_di                = $request->team_di;
            $Item->team_tl                = $request->team_tl;
            $Item->team_pm                = $request->team_pm;
            $Item->team_cm                = $request->team_cm;
            $Item->team_re                = $request->team_re;

            // Coordinator
            $Item->coord_cs               = $request->coord_cs;
            $Item->coord_facade           = $request->coord_facade;
            $Item->coord_others           = $request->coord_others;
            $Item->coord_me               = $request->coord_me;
            $Item->coord_lighting         = $request->coord_lighting;
            $Item->coord_leed_esd         = $request->coord_leed_esd;
            $Item->coord_transport        = $request->coord_transport;

            // Reviewer
            $Item->reviewer_cs            = $request->reviewer_cs;
            $Item->reviewer_mvac          = $request->reviewer_mvac;
            $Item->reviewer_facade        = $request->reviewer_facade;
            $Item->reviewer_others        = $request->reviewer_others;
            $Item->reviewer_geotechnical  = $request->reviewer_geotechnical;
            $Item->reviewer_electrical    = $request->reviewer_electrical;
            $Item->reviewer_lighting      = $request->reviewer_lighting;
            $Item->reviewer_leed_esd      = $request->reviewer_leed_esd;
            $Item->reviewer_sn_fp         = $request->reviewer_sn_fp;
            $Item->reviewer_transport     = $request->reviewer_transport;

            // Schedule
            $Item->dcr_review                         = $request->dcr_review;
            $Item->dcr_verification                   = $request->dcr_verification;
            $Item->dcr_validation                     = $request->dcr_validation;

            $Item->peer_review_review                 = $request->peer_review_review;
            $Item->peer_review_verification           = $request->peer_review_verification;
            $Item->peer_review_validation             = $request->peer_review_validation;

            $Item->submission_review                  = $request->submission_review;
            $Item->submission_verification            = $request->submission_verification;
            $Item->submission_validation              = $request->submission_validation;

            $Item->tender_review                      = $request->tender_review;
            $Item->tender_verification                = $request->tender_verification;
            $Item->tender_validation                  = $request->tender_validation;

            $Item->construction_review                = $request->construction_review;
            $Item->construction_verification          = $request->construction_verification;
            $Item->construction_validation            = $request->construction_validation;

            $Item->final_design_transport_review      = $request->final_design_transport_review;
            $Item->final_design_transport_verification= $request->final_design_transport_verification;
            $Item->final_design_transport_validation  = $request->final_design_transport_validation;

            $Item->engineering_audit_review           = $request->engineering_audit_review;
            $Item->engineering_audit_verification     = $request->engineering_audit_verification;
            $Item->engineering_audit_validation       = $request->engineering_audit_validation;

            $Item->validation_before_docs_issued      = $request->validation_before_docs_issued;
            $Item->validation_within_14days_after_docs= $request->validation_within_14days_after_docs;

            $Item->update_by = $loginBy->id ?? 'admin';
            $Item->save();

            DB::commit();
            return $this->returnUpdate('อัปเดตข้อมูลสำเร็จ', $Item);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========================================================
    // destroy
    // =========================================================
    public function destroy($id, Request $request)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();
        try {
            $Item = ProjectQualityAssurancePlan::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $Item->delete();

            $this->Log(
                $loginBy->id ?? 'admin',
                "ลบข้อมูล PQAP #{$id}",
                "ลบข้อมูล"
            );

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========================================================
    // Convert DD-MM-YYYY
    // =========================================================
    private function convertDMY($value)
    {
        if (empty($value)) return null;

        try {
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            return $value;
        }

        return $value;
    }
}
