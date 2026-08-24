<?php

namespace App\Http\Controllers;

use App\Models\CharitableContribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharitableContributionController extends Controller
{
    // =========== getList ===========
    public function getList()
    {
        $Item = CharitableContribution::orderBy('id', 'desc')->get()->toArray();

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

        $col = array(
            'id',
            'request_type',
            'event_description',
            'event_purpose',
            'organizer_name',
            'value_amount',
            'currency_code',
            'proposed_date',
            'acsc_by',
            'acsc_by_date',
            'acsc_by_status',
            'acsl_by',
            'acsl_by_date',
            'acsl_by_status',
            'approver_by',
            'approver_by_date',
            'approver_by_status',
            'approver_by_2',
            'approver_by_2_date',
            'approver_by_2_status',
            'vat_amount',
            'status',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        );

        $orderby = array(
            'event_description',
            'organizer_name',
            'request_type',
            'value_amount',
            'proposed_date',
            'created_at',
            'status',
        );

        $D = CharitableContribution::select($col);

        if (isset($Status)) {
            $D->where('status', $Status);
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
        $Item = CharitableContribution::find($id);

        if (!$Item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;
        $actorCode = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
        $requestedBy = $this->resolveRequestedEmployeeCode($request, (string) $actorCode);
        if ($requestedBy === null) {
            return $this->returnErrorData('Invalid requester employee code', 422);
        }

        // validate
        if (!isset($request->request_type)) {
            return $this->returnErrorData('กรุณาระบุประเภทคำขอ (request_type)', 404);
        }
        if (!isset($request->organizer_name)) {
            return $this->returnErrorData('กรุณาระบุชื่อผู้จัดงาน (organizer_name)', 404);
        }
        if (!isset($request->value_amount)) {
            return $this->returnErrorData('กรุณาระบุมูลค่า (value_amount)', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุวันที่เสนอ (proposed_date)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = new CharitableContribution();
            $Item->request_type             = $request->request_type;
            $Item->event_description        = $request->event_description ?? null;
            $Item->event_purpose            = $request->event_purpose ?? null;
            $Item->organizer_name           = $request->organizer_name;
            $Item->contribution_description = $request->contribution_description ?? null;

            $Item->value_amount             = $request->value_amount ?? 0;
            $Item->currency_code            = $this->normalizeCurrencyCodeInput($request->currency_code ?? null);
            $Item->vat_amount               = $request->vat_amount ?? 0;
            $Item->proposed_date            = $this->normalizeDateTimeInput($request->proposed_date);

            $Item->acsc_by                  = $request->acsc_by ?? null;
            $Item->acsc_by_date             = $this->normalizeDateTimeInput($request->acsc_by_date ?? null);
            $Item->acsc_by_status           = $request->acsc_by_status ?? null;
            $Item->acsl_by                  = $request->acsl_by ?? null;
            $Item->acsl_by_date             = $this->normalizeDateTimeInput($request->acsl_by_date ?? null);
            $Item->acsl_by_status           = $request->acsl_by_status ?? null;
            $Item->approver_by              = $request->approver_by ?? null;
            $Item->approver_by_date         = $this->normalizeDateTimeInput($request->approver_by_date ?? null);
            $Item->approver_by_status       = $request->approver_by_status ?? null;
            $Item->approver_by_2            = $request->approver_by_2 ?? null;
            $Item->approver_by_2_date       = $this->normalizeDateTimeInput($request->approver_by_2_date ?? null);
            $Item->approver_by_2_status     = $request->approver_by_2_status ?? null;

            $Item->status                   = $request->status ?? 'pending';
            $Item->create_by                = $requestedBy;

            $Item->save();

            $this->logDocumentCreateAudit($request, $Item);

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
        $requestedBy = null;
        if ($request->has('requested_by')) {
            $actorCode = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $requestedBy = $this->resolveRequestedEmployeeCode($request, (string) $actorCode);
            if ($requestedBy === null) {
                return $this->returnErrorData('Invalid requester employee code', 422);
            }
        }

        // ===== Validate Minimal Required Fields =====
        if (!isset($request->request_type)) {
            return $this->returnErrorData('กรุณาระบุประเภทคำขอ (request_type)', 404);
        }
        if (!isset($request->organizer_name)) {
            return $this->returnErrorData('กรุณาระบุชื่อผู้จัดงาน (organizer_name)', 404);
        }
        if (!isset($request->value_amount)) {
            return $this->returnErrorData('กรุณาระบุมูลค่า (value_amount)', 404);
        }
        if (!isset($request->proposed_date)) {
            return $this->returnErrorData('กรุณาระบุวันที่เสนอ (proposed_date)', 404);
        }

        DB::beginTransaction();

        try {

            $Item = CharitableContribution::find($id);

            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            // ===== Update Fields =====
            $Item->request_type             = $request->request_type;
            $Item->event_description        = $request->event_description ?? null;
            $Item->event_purpose            = $request->event_purpose ?? null;
            $Item->organizer_name           = $request->organizer_name;
            $Item->contribution_description = $request->contribution_description ?? null;

            $Item->value_amount             = $request->value_amount ?? 0;
            $Item->currency_code            = $this->normalizeCurrencyCodeInput($request->currency_code ?? null, $Item->currency_code ?? 'THB');
            $Item->vat_amount               = $request->vat_amount ?? 0;
            $Item->proposed_date            = $this->normalizeDateTimeInput($request->proposed_date);

            $Item->acsc_by                  = $request->acsc_by ?? null;
            $Item->acsc_by_date             = $this->normalizeDateTimeInput($request->acsc_by_date ?? null);
            $Item->acsc_by_status           = $request->acsc_by_status ?? null;
            $Item->acsl_by                  = $request->acsl_by ?? null;
            $Item->acsl_by_date             = $this->normalizeDateTimeInput($request->acsl_by_date ?? null);
            $Item->acsl_by_status           = $request->acsl_by_status ?? null;
            $Item->approver_by              = $request->approver_by ?? null;
            $Item->approver_by_date         = $this->normalizeDateTimeInput($request->approver_by_date ?? null);
            $Item->approver_by_status       = $request->approver_by_status ?? null;
            $Item->approver_by_2            = $request->approver_by_2 ?? null;
            $Item->approver_by_2_date       = $this->normalizeDateTimeInput($request->approver_by_2_date ?? null);
            $Item->approver_by_2_status     = $request->approver_by_2_status ?? null;

            $Item->status                   = $request->status ?? $Item->status;
            if ($requestedBy !== null) {
                $Item->create_by = $requestedBy;
            }
            $Item->update_by                = $loginBy->employee_code ?? $loginBy->id ?? 'admin';

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

            $Item = CharitableContribution::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            $Item->delete();

            // log
            $userId      = $loginBy->employee_code ?? $loginBy->id ?? 'admin';
            $type        = 'ลบคำขอสนับสนุนการกุศล';
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
