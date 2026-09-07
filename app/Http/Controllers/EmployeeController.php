<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    // GET /api/employees?search=...&limit=...&is_approver=0|1
    public function getList(Request $request)
    {
        try {
            $q = DB::table('employees')
                ->whereNull('deleted_at');

            if ($request->filled('committee_name')) {
                $committeeName = trim((string) $request->input('committee_name'));
                $q->whereExists(function ($committeeQuery) use ($committeeName) {
                    $committeeQuery->select(DB::raw(1))
                        ->from('committee_employees')
                        ->join('committees', 'committees.id', '=', 'committee_employees.committee_id')
                        ->whereColumn('committee_employees.employee_code', 'employees.code')
                        ->whereNull('committee_employees.deleted_at')
                        ->whereNull('committees.deleted_at')
                        ->whereRaw('LOWER(TRIM(committees.name)) = ?', [strtolower($committeeName)]);
                });
            }

            $applyMultiLikeFilter = function (string $column, $rawValue) use ($q) {
                $values = is_array($rawValue) ? $rawValue : [$rawValue];
                $values = array_values(array_filter(array_map(function ($v) {
                    return trim((string) $v);
                }, $values), function ($v) {
                    return $v !== '';
                }));

                if (count($values) === 1) {
                    $q->where($column, 'like', "%{$values[0]}%");
                    return;
                }

                if (count($values) > 1) {
                    $q->where(function ($w) use ($column, $values) {
                        foreach ($values as $value) {
                            $w->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }
            };

            $applyMultiLikeFilter('level_name', $request->input('level_name'));
            $applyMultiLikeFilter('title_name', $request->input('title_name'));
            $applyMultiLikeFilter('department_name', $request->input('department_name'));
            $applyMultiLikeFilter(
                'employee_type_name',
                $request->input('employee_type_name', $request->input('emmployee_type_name'))
            );

            if ($request->has('is_approver')) {
                $raw = $request->input('is_approver');
                $val = filter_var($raw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 1],
                ]);

                if ($val === false) {
                    return $this->returnErrorData('is_approver ต้องเป็น 0 หรือ 1', 404);
                }

                $q->where('is_approver', (int) $val);
            }

            if ($request->filled('search')) {
                $s = trim((string) $request->input('search'));

                $q->where(function ($w) use ($s) {
                    $w->where('initial', 'like', '%' . $s . '%')
                        ->orWhere('firstname', 'like', '%' . $s . '%')
                        ->orWhere('lastname', 'like', '%' . $s . '%')
                        ->orWhere('department_name', 'like', '%' . $s . '%')
                        ->orWhere('code', 'like', '%' . $s . '%');
                });
            }

            $limit = (int) $request->input('limit', 50);
            if ($limit <= 0) {
                $limit = 50;
            }
            $maxLimit = $request->filled('committee_name') ? 20 : 200;
            if ($limit > $maxLimit) {
                $limit = $maxLimit;
            }

            $items = $q->orderBy('firstname')
                ->orderBy('lastname')
                ->limit($limit)
                ->get([
                    'id',
                    'code',
                    'initial',
                    'firstname',
                    'lastname',
                    'email',
                    'level_name',
                    'title_name',
                    'department_name',
                    'employee_type_name',
                    'is_approver',
                    'active',
                ]);

            return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
        } catch (\Throwable $e) {
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }
}
