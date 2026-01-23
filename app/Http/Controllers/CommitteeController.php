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

    // =========== getPage (DataTables style) ===========
    public function getPage(Request $request)
    {
        try {
            $columns = $request->columns;
            $length  = (int) ($request->length ?? 10);
            $order   = $request->order;
            $search  = $request->search;
            $start   = (int) ($request->start ?? 0);
            $page    = $length > 0 ? ($start / $length + 1) : 1;
            $withEmployees = $this->parseBoolean($request->input('with_employees', true));

            $col = [
                'id',
                'name',
                'create_by',
                'update_by',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $orderby = [
                '',
                'name',
                'create_by',
                'update_by',
                'created_at',
            ];

            $D = DB::table('committees')->select($col)->whereNull('deleted_at');

            if (isset($request->search_name) && trim((string) $request->search_name) !== '') {
                $D->where('name', 'like', '%' . trim((string) $request->search_name) . '%');
            }

            if (($orderby[$order[0]['column']] ?? false) && isset($order[0]['dir'])) {
                $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
            } else {
                $D->orderBy('id', 'desc');
            }

            if (($search['value'] ?? '') !== '' && ($search['value'] ?? null) !== null) {
                $s = (string) $search['value'];
                $D->where(function ($query) use ($s) {
                    $query->orWhere('name', 'like', '%' . $s . '%')
                        ->orWhere('create_by', 'like', '%' . $s . '%')
                        ->orWhere('update_by', 'like', '%' . $s . '%');
                });
            }

            $d = $D->paginate($length, ['*'], 'page', $page);

            if ($d->isNotEmpty()) {
                $No = (($page - 1) * $length);
                for ($i = 0; $i < count($d); $i++) {
                    $No = $No + 1;
                    $d[$i]->No = $No;
                }
            }

            if ($withEmployees && $d->isNotEmpty()) {
                $committeeIds = [];
                foreach ($d->items() as $row) {
                    if (isset($row->id)) {
                        $committeeIds[] = (int) $row->id;
                    }
                }
                $committeeIds = array_values(array_unique($committeeIds));

                $map = [];
                if (!empty($committeeIds)) {
                    $rows = DB::table('committee_employees')
                        ->join('employees', 'committee_employees.employee_code', '=', 'employees.code')
                        ->whereIn('committee_employees.committee_id', $committeeIds)
                        ->select(
                            'committee_employees.committee_id',
                            'employees.code',
                            'employees.firstname',
                            'employees.lastname',
                            'employees.email',
                            'employees.department_name'
                        )
                        ->orderBy('employees.firstname')
                        ->orderBy('employees.lastname')
                        ->get();

                    foreach ($rows as $r) {
                        $cid = (int) $r->committee_id;
                        if (!isset($map[$cid])) {
                            $map[$cid] = [];
                        }
                        $map[$cid][] = [
                            'code' => $r->code,
                            'firstname' => $r->firstname,
                            'lastname' => $r->lastname,
                            'email' => $r->email,
                            'department_name' => $r->department_name,
                        ];
                    }
                }

                for ($i = 0; $i < count($d); $i++) {
                    $cid = (int) ($d[$i]->id ?? 0);
                    $d[$i]->employees = $map[$cid] ?? [];
                }
            }

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
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
            return $this->returnErrorData('employee_codes/employees ต้องเป็น array (หรือ string คั่นด้วย ,) ของรหัสพนักงาน', 404);
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
                return $this->returnErrorData('employee_codes/employees ต้องเป็น array (หรือ string คั่นด้วย ,) ของรหัสพนักงาน', 404);
            }

            $removeProvided = false;
            $removeCodes = $this->extractEmployeeCodesRemove($request, $removeProvided);
            if ($removeCodes === null) {
                DB::rollBack();
                return $this->returnErrorData('employee_codes_remove ต้องเป็น array (หรือ string คั่นด้วย ,) ของรหัสพนักงาน', 404);
            }

            $addProvided = false;
            $addCodes = $this->extractEmployeeCodesAdd($request, $addProvided);
            if ($addCodes === null) {
                DB::rollBack();
                return $this->returnErrorData('employee_codes_add ต้องเป็น array (หรือ string คั่นด้วย ,) ของรหัสพนักงาน', 404);
            }

            if ($codesProvided) {
                if (!empty($removeCodes)) {
                    $codes = array_values(array_diff($codes, $removeCodes));
                }

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
            } else {
                // Partial update support (add/remove) without replacing the entire list
                if (!empty($removeCodes)) {
                    DB::table('committee_employees')
                        ->where('committee_id', $id)
                        ->whereIn('employee_code', $removeCodes)
                        ->delete();
                }

                if (!empty($addCodes)) {
                    $missing = $this->validateEmployeeCodesExist($addCodes);
                    if (!empty($missing)) {
                        DB::rollBack();
                        return $this->returnErrorData('ไม่พบรหัสพนักงาน: ' . implode(', ', $missing), 404);
                    }

                    $now = now();
                    $rows = [];
                    foreach ($addCodes as $code) {
                        if (!empty($removeCodes) && in_array($code, $removeCodes, true)) {
                            continue;
                        }
                        $rows[] = [
                            'committee_id' => $id,
                            'employee_code' => $code,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('committee_employees')->insertOrIgnore($rows);
                    }
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
        $provided = $request->exists('employee_codes') || $request->exists('employees');
        if (!$provided) {
            return [];
        }

        $rawEmployeeCodes = $request->input('employee_codes', null);
        $rawEmployees = $request->input('employees', null);

        // Prefer `employees` when it looks like array of objects/arrays with code fields.
        $raw = $rawEmployeeCodes !== null ? $rawEmployeeCodes : $rawEmployees;
        if ($rawEmployeeCodes !== null && $rawEmployees !== null) {
            if ($this->rawLooksLikeEmployeeObjects($rawEmployees)) {
                $raw = $rawEmployees;
            }
        }

        return $this->normalizeEmployeeCodes($raw);
    }

    private function extractEmployeeCodesRemove(Request $request, bool &$provided): ?array
    {
        $keys = [
            'employee_codes_remove',
            'remove_employee_codes',
            'employee_codes_removed',
            'employee_codes_delete',
            'employee_codes_deleted',
            'deleted_employee_codes',
        ];

        $provided = false;
        $all = [];
        foreach ($keys as $key) {
            if (!$request->exists($key)) {
                continue;
            }
            $provided = true;
            $codes = $this->normalizeEmployeeCodes($request->input($key));
            if ($codes === null) {
                return null;
            }
            $all = array_merge($all, $codes);
        }

        return array_values(array_unique($all));
    }

    private function extractEmployeeCodesAdd(Request $request, bool &$provided): ?array
    {
        $keys = [
            'employee_codes_add',
            'add_employee_codes',
            'employee_codes_to_add',
        ];

        $provided = false;
        $all = [];
        foreach ($keys as $key) {
            if (!$request->exists($key)) {
                continue;
            }
            $provided = true;
            $codes = $this->normalizeEmployeeCodes($request->input($key));
            if ($codes === null) {
                return null;
            }
            $all = array_merge($all, $codes);
        }

        return array_values(array_unique($all));
    }

    private function rawLooksLikeEmployeeObjects($raw): bool
    {
        if (!is_array($raw) || empty($raw)) {
            return false;
        }

        $first = $raw[array_key_first($raw)];
        if (is_array($first)) {
            return array_key_exists('code', $first) || array_key_exists('employee_code', $first) || array_key_exists('employeeCode', $first);
        }
        if (is_object($first)) {
            return isset($first->code) || isset($first->employee_code) || isset($first->employeeCode);
        }

        return false;
    }

    private function normalizeEmployeeCodes($raw): ?array
    {
        if ($raw === null) {
            return [];
        }

        // Allow comma-separated string (e.g. "E001,E002")
        if (is_string($raw)) {
            $s = trim($raw);
            if ($s === '') {
                return [];
            }
            $parts = preg_split('/\s*,\s*/', $s) ?: [];
            $parts = array_filter(array_map('trim', $parts), fn ($v) => $v !== '');
            return array_values(array_unique($parts));
        }

        if (!is_array($raw)) {
            return null;
        }

        $codes = [];
        foreach ($raw as $item) {
            $code = null;

            if (is_string($item) || is_numeric($item)) {
                $code = (string) $item;
            } elseif (is_array($item)) {
                $code = $item['code'] ?? $item['employee_code'] ?? $item['employeeCode'] ?? null;
            } elseif (is_object($item)) {
                $code = $item->code ?? $item->employee_code ?? $item->employeeCode ?? null;
            }

            if ($code === null) {
                continue;
            }

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

    private function parseBoolean($value): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            return (bool) $value;
        }
        return (bool) $parsed;
    }
}
