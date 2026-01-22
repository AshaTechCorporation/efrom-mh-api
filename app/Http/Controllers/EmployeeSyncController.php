<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EmployeeSyncController extends Controller
{
    public function sync(Request $request)
    {
        $url = config('services.hrm_employee.url');
        if (empty($url)) {
            return response()->json([
                'error' => true,
                'message' => 'HRM employee API URL is not configured.',
            ], 500);
        }

        $lastSync = $request->input('last_sync');
        $query = [];
        if (!empty($lastSync)) {
            $param = config('services.hrm_employee.updated_param', 'updatedAt');
            $query[$param] = $lastSync;
        }

        $client = Http::timeout(60)->acceptJson();
        if (!config('services.hrm_employee.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        $response = $client->get($url, $query);

        if (!$response->successful()) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to fetch employees from HRM API.',
                'status' => $response->status(),
                'body' => $response->body(),
            ], 502);
        }

        $payload = $response->json();
        $employees = [];

        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            $employees = $payload['data'];
        } elseif (is_array($payload) && isset($payload['data']['data']) && is_array($payload['data']['data'])) {
            $employees = $payload['data']['data'];
        } elseif (is_array($payload)) {
            $employees = $payload;
        }

        if (empty($employees)) {
            return response()->json([
                'error' => false,
                'message' => 'No employees to sync.',
                'synced' => 0,
            ]);
        }

        $now = now();
        $rows = [];

        foreach ($employees as $employee) {
            $code = data_get($employee, 'code');
            if (empty($code)) {
                continue;
            }

            $headFirst = data_get($employee, 'head.firstname');
            $headLast = data_get($employee, 'head.lastname');
            $headName = trim(trim((string) $headFirst) . ' ' . trim((string) $headLast));
            $headName = $headName !== '' ? $headName : null;

            $rows[] = [
                'code' => $code,
                'username' => data_get($employee, 'username'),
                'password' => data_get($employee, 'password'),
                'firstname' => data_get($employee, 'firstname'),
                'lastname' => data_get($employee, 'lastname'),
                'email' => data_get($employee, 'email'),
                'birth_date' => data_get($employee, 'birthDate'),
                'register_date' => data_get($employee, 'registerDate'),
                'pass_probation_date' => data_get($employee, 'passProbationDate'),
                'sex' => data_get($employee, 'sex'),
                'title_id' => data_get($employee, 'title.id'),
                'title_name' => data_get($employee, 'title.name'),
                'level_id' => data_get($employee, 'level.id'),
                'level_name' => data_get($employee, 'level.name'),
                'department_id' => data_get($employee, 'department.id'),
                'department_name' => data_get($employee, 'department.name'),
                'employee_type_id' => data_get($employee, 'employeeType.id'),
                'employee_type_name' => data_get($employee, 'employeeType.name'),
                'work_shift_id' => data_get($employee, 'workShift.id'),
                'work_shift_name' => data_get($employee, 'workShift.name'),
                'head_id' => data_get($employee, 'head.id'),
                'head_name' => $headName,
                'initial' => data_get($employee, 'initial'),
                'is_approver' => (bool) data_get($employee, 'isApprover', false),
                'next_quota_update' => data_get($employee, 'nextQuotaUpdate'),
                'employee_status' => data_get($employee, 'employeeStatus'),
                'active' => data_get($employee, 'active'),
                'current_start_period' => data_get($employee, 'currentStartPeriod'),
                'current_end_period' => data_get($employee, 'currentEndPeriod'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return response()->json([
                'error' => false,
                'message' => 'No valid employees to sync.',
                'synced' => 0,
            ]);
        }

        DB::table('employees')->upsert(
            $rows,
            ['code'],
            [
                'username',
                'password',
                'firstname',
                'lastname',
                'email',
                'birth_date',
                'register_date',
                'pass_probation_date',
                'sex',
                'title_id',
                'title_name',
                'level_id',
                'level_name',
                'department_id',
                'department_name',
                'employee_type_id',
                'employee_type_name',
                'work_shift_id',
                'work_shift_name',
                'head_id',
                'head_name',
                'initial',
                'is_approver',
                'next_quota_update',
                'employee_status',
                'active',
                'current_start_period',
                'current_end_period',
                'updated_at',
            ]
        );

        return response()->json([
            'error' => false,
            'message' => 'Employees synced.',
            'synced' => count($rows),
        ]);
    }
}
