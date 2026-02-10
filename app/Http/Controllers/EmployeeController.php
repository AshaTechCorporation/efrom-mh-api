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
                ->whereNull('deleted_at')
                ->where('active', 'PER');

            if ($request->filled('level_name')) {
                $s = trim((string) $request->input('level_name'));
                $q->where('level_name', 'like', "%{$s}%");
            }
            if ($request->filled('title_name')) {
                $s = trim((string) $request->input('title_name'));
                $q->where('title_name', 'like', "%{$s}%");
            }
            if ($request->filled('department_name')) {
                $s = trim((string) $request->input('department_name'));
                $q->where('department_name', 'like', "%{$s}%");
            }
            $employeeTypeName = $request->input('employee_type_name', $request->input('emmployee_type_name'));
            if (isset($employeeTypeName) && trim((string) $employeeTypeName) !== '') {
                $s = trim((string) $employeeTypeName);
                $q->where('employee_type_name', 'like', "%{$s}%");
            }

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
                     $w->where('initial', 'like', "%{$s}%")
                        ->orWhere('firstname', 'like', "%{$s}%")
                        ->orWhere('lastname', 'like', "%{$s}%")
                        ->orWhere('department_name', 'like', "%{$s}%");
                });
            }

            $limit = (int) $request->input('limit', 50);
            if ($limit <= 0) {
                $limit = 50;
            }
            if ($limit > 200) {
                $limit = 200;
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
