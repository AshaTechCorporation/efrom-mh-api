<?php

namespace App\Http\Controllers;

use App\Models\SubConsultantAssessments;
use App\Models\SubConsultantAssessmentReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubConsultantAssessmentsController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = SubConsultantAssessments::with('references')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

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

        $Status  = $request->status;

        // คอลัมน์ที่ select (เอาที่ใช้จริงบนหน้า list)
        $col = array(
            'id',
            'form_code',
            'to',
            'circ',
            'company',
            'item1_total_score',
            'recommendation',
            'status',
            'assessed_by',
            'assessed_by_date',
            'approved_by',
            'approved_by_date',
            'acknowledged_by',
            'acknowledged_by_date',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        );

        // mapping สำหรับ sort (DataTables column index)
        $orderby = array(
            '',
            'form_code',
            'to',
            'circ',
            'company',
            'item1_total_score',
            'recommendation',
            'status',
            'created_at',
        );

        $D = SubConsultantAssessments::select($col);

        if (isset($Status)) {
            $D->where('status', $Status);
        }

        if ($orderby[$order[0]['column']] ?? false) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        } else {
            $D->orderBy('id', 'desc');
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
        $Item = SubConsultantAssessments::with('references')->find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        // ===== Validate Required =====
        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ To (to)', 404);
        }
        if (!isset($request->circ)) {
            return $this->returnErrorData('กรุณาระบุ Circ (circ)', 404);
        }
        if (!isset($request->scope_of_service)) {
            return $this->returnErrorData('กรุณาระบุ Scope of Service (scope_of_service)', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ Company (company)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new SubConsultantAssessments();

            // Document info
            $Item->form_code  = $request->form_code ?? null;
            $Item->form_title = $request->form_title ?? null;

            // Header
            $Item->to              = $request->to;
            $Item->circ            = $request->circ;
            $Item->scope_of_service = $request->scope_of_service ?? null;

            // Information used for Assessment (checkbox)
            $Item->info_company_profile_biodata       = $request->info_company_profile_biodata ?? null;
            $Item->info_site_visit                    = $request->info_site_visit ?? null;
            $Item->info_previous_evaluation_record    = $request->info_previous_evaluation_record ?? null;
            $Item->info_project_reference_certificates= $request->info_project_reference_certificates ?? null;
            $Item->info_previous_assessment_record    = $request->info_previous_assessment_record ?? null;
            $Item->info_iso_certificates              = $request->info_iso_certificates ?? null;

            // Item 1
            $Item->company = $request->company;

            $Item->score_experience_since_establishment = $request->score_experience_since_establishment ?? 0;
            $Item->score_fully_qualified_staff         = $request->score_fully_qualified_staff ?? 0;
            $Item->score_completed_similar_projects    = $request->score_completed_similar_projects ?? 0;

            // optional total (ถ้าไม่ส่งมา จะคำนวณให้)
            $Item->item1_total_score = $request->item1_total_score ?? (
                (int)($Item->score_experience_since_establishment ?? 0) +
                (int)($Item->score_fully_qualified_staff ?? 0) +
                (int)($Item->score_completed_similar_projects ?? 0)
            );

            // Item 2
            $Item->ems_iso_14001   = $request->ems_iso_14001 ?? null;
            $Item->ems_ohsas_18001 = $request->ems_ohsas_18001 ?? null;
            $Item->ems_iso_45001   = $request->ems_iso_45001 ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? null; // accept | not_accept
            $Item->recommendation_reason = $request->recommendation_reason ?? null;

            // Decision #3
            $Item->decision_sub_consultant_list = $request->decision_sub_consultant_list ?? null;

            // Remark
            $Item->remark = $request->remark ?? null;

            // Signatures/Approval
            $Item->assessed_by     = $request->assessed_by ?? null;
            $Item->assessed_by_date   = $request->assessed_by_date ?? null;
            $Item->assessed_by_status = $request->assessed_by_status ?? null;

            $Item->approved_by     = $request->approved_by ?? null;
            $Item->approved_by_date   = $request->approved_date ?? null;
            $Item->approved_by_status = $request->approved_by_status ?? null;

            $Item->acknowledged_by     = $request->acknowledged_by ?? null;
            $Item->acknowledged_by_date   = $request->acknowledged_by_date ?? null;
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? null;

            // Overall status
            $Item->status = $request->status ?? 'draft';

            // Control
            $Item->create_by = $loginBy->id ?? 'admin';

            $Item->save();

            // ===== References (Item 3) =====
            // รูปแบบที่รองรับ: references: [{seq:1, reference_name:"", opinion:"good"}, ...]
            $refs = $request->references ?? [];
            if (is_array($refs) && count($refs) > 0) {
                foreach ($refs as $r) {
                    $Ref = new SubConsultantAssessmentReferences();
                    $Ref->assessment_id   = $Item->id;
                    $Ref->seq             = $r['seq'] ?? null;
                    $Ref->reference_name  = $r['reference_name'] ?? null;
                    $Ref->opinion         = $r['opinion'] ?? null;
                    $Ref->create_by       = $loginBy->id ?? 'admin';
                    $Ref->save();
                }
            }

            DB::commit();

            $Item = SubConsultantAssessments::with('references')->find($Item->id);
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

        // ===== Validate Required =====
        if (!isset($request->to)) {
            return $this->returnErrorData('กรุณาระบุ To (to)', 404);
        }
        if (!isset($request->circ)) {
            return $this->returnErrorData('กรุณาระบุ Circ (circ)', 404);
        }
        if (!isset($request->scope_of_service)) {
            return $this->returnErrorData('กรุณาระบุ Scope of Service (scope_of_service)', 404);
        }
        if (!isset($request->company)) {
            return $this->returnErrorData('กรุณาระบุ Company (company)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = SubConsultantAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            // Document info
            $Item->form_code  = $request->form_code ?? $Item->form_code;
            $Item->form_title = $request->form_title ?? $Item->form_title;

            // Header
            $Item->to               = $request->to;
            $Item->circ             = $request->circ;
            $Item->scope_of_service = $request->scope_of_service ?? null;

            // Info used
            $Item->info_company_profile_biodata        = $request->info_company_profile_biodata ?? null;
            $Item->info_site_visit                     = $request->info_site_visit ?? null;
            $Item->info_previous_evaluation_record     = $request->info_previous_evaluation_record ?? null;
            $Item->info_project_reference_certificates = $request->info_project_reference_certificates ?? null;
            $Item->info_previous_assessment_record     = $request->info_previous_assessment_record ?? null;
            $Item->info_iso_certificates               = $request->info_iso_certificates ?? null;

            // Item 1
            $Item->company = $request->company;

            $Item->score_experience_since_establishment = $request->score_experience_since_establishment ?? 0;
            $Item->score_fully_qualified_staff          = $request->score_fully_qualified_staff ?? 0;
            $Item->score_completed_similar_projects     = $request->score_completed_similar_projects ?? 0;

            $Item->item1_total_score = $request->item1_total_score ?? (
                (int)($Item->score_experience_since_establishment ?? 0) +
                (int)($Item->score_fully_qualified_staff ?? 0) +
                (int)($Item->score_completed_similar_projects ?? 0)
            );

            // Item 2
            $Item->ems_iso_14001   = $request->ems_iso_14001 ?? null;
            $Item->ems_ohsas_18001 = $request->ems_ohsas_18001 ?? null;
            $Item->ems_iso_45001   = $request->ems_iso_45001 ?? null;

            // Recommendation
            $Item->recommendation        = $request->recommendation ?? $Item->recommendation;
            $Item->recommendation_reason = $request->recommendation_reason ?? $Item->recommendation_reason;

            // Decision #3
            $Item->decision_sub_consultant_list = $request->decision_sub_consultant_list ?? $Item->decision_sub_consultant_list;

            // Remark
            $Item->remark = $request->remark ?? $Item->remark;

            // Signatures/Approval
            $Item->assessed_by     = $request->assessed_by ?? $Item->assessed_by;
            $Item->assessed_by_date   = $request->assessed_by_date ?? $Item->assessed_date;
            $Item->assessed_by_status = $request->assessed_by_status ?? $Item->assessed_by_status;

            $Item->approved_by     = $request->approved_by ?? $Item->approved_by;
            $Item->approved_by_date   = $request->approved_by_date ?? $Item->approved_date;
            $Item->approved_by_status = $request->approved_by_status ?? $Item->approved_by_status;

            $Item->acknowledged_by     = $request->acknowledged_by ?? $Item->acknowledged_by;
            $Item->acknowledged_by_date   = $request->acknowledged_by_date ?? $Item->acknowledged_date;
            $Item->acknowledged_by_status = $request->acknowledged_by_status ?? $Item->acknowledged_status;

            // Overall status
            $Item->status = $request->status ?? $Item->status;

            // Control
            $Item->update_by = $loginBy->id ?? 'admin';

            $Item->save();

            // ===== References: ล้างของเก่าแล้วใส่ใหม่ (ง่ายและชัวร์) =====
            SubConsultantAssessmentReferences::where('assessment_id', $Item->id)->delete();

            $refs = $request->references ?? [];
            if (is_array($refs) && count($refs) > 0) {
                foreach ($refs as $r) {
                    $Ref = new SubConsultantAssessmentReferences();
                    $Ref->assessment_id   = $Item->id;
                    $Ref->seq             = $r['seq'] ?? null;
                    $Ref->reference_name  = $r['reference_name'] ?? null;
                    $Ref->opinion         = $r['opinion'] ?? null;
                    $Ref->create_by       = $loginBy->id ?? 'admin';
                    $Ref->save();
                }
            }

            DB::commit();

            $Item = SubConsultantAssessments::with('references')->find($Item->id);
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

            $Item = SubConsultantAssessments::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            // delete children first (soft delete)
            SubConsultantAssessmentReferences::where('assessment_id', $Item->id)->delete();

            $Item->delete();

            // log
            $userId      = $loginBy->id ?? 'admin';
            $type        = 'ลบแบบประเมินผู้รับเหมาช่วง';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' #' . $Item->id;
            $this->Log($userId, $description, $type);

            DB::commit();

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);

        } catch (\Throwable $e) {

            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }
}
