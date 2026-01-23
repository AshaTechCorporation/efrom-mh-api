<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitteeController extends Controller
{
    // =========== getList ===========
    public function getList(Request $request)
    {
        try {
            $q = DB::table('committees')->whereNull('deleted_at');

            if (isset($request->search) && trim((string) $request->search) !== '') {
                $s = trim((string) $request->search);
                $q->where('name', 'like', "%{$s}%");
            }

            $items = $q->orderBy('id', 'desc')->get();

            $no = 1;
            foreach ($items as $it) {
                $it->No = $no++;
            }

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
        } catch (\Throwable $e) {
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== index ===========
    public function index(Request $request)
    {
        return $this->getList($request);
    }

    // =========== show ===========
    public function show($id)
    {
        try {
            $committee = DB::table('committees')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();

            if (!$committee) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $employees = DB::table('committee_employees')
                ->join('employees', 'committee_employees.employee_code', '=', 'employees.code')
                ->where('committee_employees.committee_id', $id)
                ->select('employees.*')
                ->orderBy('employees.firstname')
                ->orderBy('employees.lastname')
                ->get();

            $committee->employees = $employees;

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $committee);
        } catch (\Throwable $e) {
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== store ===========
    public function store(Request $request)
    {
        $loginBy = $request->login_by;

        if (!isset($request->committee_name) || trim((string) $request->committee_name) === '') {
            return $this->returnErrorData('กรุณาระบุชื่อคณะกรรมการ (committee_name)', 404);
        }

        $codesProvided = false;
        $codes = $this->extractEmployeeCodes($request, $codesProvided);
        if ($codes === null) {
            return $this->returnErrorData('employee_codes ต้องเป็น array ของรหัสพนักงาน', 404);
        }

        DB::beginTransaction();
        try {
            $committee = new Committee();
            $committee->name = trim((string) $request->committee_name);
            $committee->create_by = $loginBy->id ?? ($request->create_by ?? 'admin');
            $committee->update_by = $loginBy->id ?? ($request->update_by ?? null);
            $committee->save();

            if (!empty($codes)) {
                $missing = $this->validateEmployeeCodesExist($codes);
                if (!empty($missing)) {
                    DB::rollBack();
                    return $this->returnErrorData('ไม่พบรหัสพนักงาน: ' . implode(', ', $missing), 404);
                }

                $now = now();
                $rows = [];
                foreach ($codes as $code) {
                    $rows[] = [
                        'committee_id' => $committee->id,
                        'employee_code' => $code,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('committee_employees')->insert($rows);
            }

            $data = $this->loadCommitteeWithEmployees($committee->id);

            DB::commit();
            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $data);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    // =========== update (PUT) ===========
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        DB::beginTransaction();
        try {
            $committee = Committee::query()
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();

            if (!$committee) {
                DB::rollBack();
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            if (isset($request->committee_name)) {
                $name = trim((string) $request->committee_name);
                if ($name === '') {
                    DB::rollBack();
                    return $this->returnErrorData('ชื่อคณะกรรมการ (committee_name) ห้ามว่าง', 404);
                }
                $committee->name = $name;
            }

            $committee->update_by = $loginBy->id ?? ($request->update_by ?? $committee->update_by);
            $committee->save();

            $codesProvided = false;
            $codes = $this->extractEmployeeCodes($request, $codesProvided);
            if ($codes === null) {
                DB::rollBack();
                return $this->returnErrorData('employee_codes ต้องเป็น array ของรหัสพนักงาน', 404);
            }

            if ($codesProvided) {
                $missing = $this->validateEmployeeCodesExist($codes);
                if (!empty($missing)) {
                    DB::rollBack();
                    return $this->returnErrorData('ไม่พบรหัสพนักงาน: ' . implode(', ', $missing), 404);
                }

                DB::table('committee_employees')->where('committee_id', $id)->delete();

                if (!empty($codes)) {
                    $now = now();
                    $rows = [];
                    foreach ($codes as $code) {
                        $rows[] = [
                            'committee_id' => $id,
                            'employee_code' => $code,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('committee_employees')->insert($rows);
                }
            }

            $data = $this->loadCommitteeWithEmployees($id);

            DB::commit();
            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $data);
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
            $committee = Committee::query()
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();

            if (!$committee) {
                DB::rollBack();
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            DB::table('committee_employees')->where('committee_id', $id)->delete();
            $committee->update_by = $loginBy->id ?? ($committee->update_by ?? 'admin');
            $committee->save();
            $committee->delete();

            DB::commit();
            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }

    private function extractEmployeeCodes(Request $request, bool &$provided): ?array
    {
        $provided = $request->has('employee_codes') || $request->has('employees');
        if (!$provided) {
            return [];
        }

        $raw = $request->input('employee_codes', $request->input('employees', []));
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw)) {
            return null;
        }

        $codes = [];
        foreach ($raw as $code) {
            $c = trim((string) $code);
            if ($c !== '') {
                $codes[] = $c;
            }
        }

        return array_values(array_unique($codes));
    }

    private function validateEmployeeCodesExist(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $found = DB::table('employees')
            ->whereIn('code', $codes)
            ->pluck('code')
            ->all();

        $foundMap = array_fill_keys($found, true);

        $missing = [];
        foreach ($codes as $code) {
            if (!isset($foundMap[$code])) {
                $missing[] = $code;
            }
        }

        return $missing;
    }

    private function loadCommitteeWithEmployees(int $id)
    {
        $committee = DB::table('committees')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        $employees = DB::table('committee_employees')
            ->join('employees', 'committee_employees.employee_code', '=', 'employees.code')
            ->where('committee_employees.committee_id', $id)
            ->select('employees.*')
            ->orderBy('employees.firstname')
            ->orderBy('employees.lastname')
            ->get();

        if ($committee) {
            $committee->employees = $employees;
        }

        return $committee;
    }
}
