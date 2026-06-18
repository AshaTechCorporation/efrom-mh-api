<?php

namespace App\Http\Controllers;

use App\Models\ConceptDesignReview;
use App\Models\ConstructionValidation;
use App\Models\EngineeringAuditReview;
use App\Models\LeedReview;
use App\Models\SchematicDesignReview;
use App\Models\SubmissionReview;
use App\Models\TenderCsaReview;
use App\Models\TenderCsaVerification;
use App\Models\TenderMepReview;
use App\Models\TenderMepVerification;
use App\Models\TenderReview;
use App\Models\ValueEngineeringReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjectReviewPageController extends JsonPayloadCrudController
{
    private const EMPLOYEE_REFERENCE_KEYS = [
        'prepared_by',
        'preparedBy',
        'create_by',
        'createBy',
        'filled_in_by',
        'filledInBy',
        'reviewed_by',
        'reviewedBy',
        'reviewer_for_action',
        'reviewerForAction',
        'responded_by',
        'respondedBy',
        'completed_by',
        'completedBy',
        'signed_by',
        'signedBy',
        'signed_by_tl',
        'signedByTL',
        'signed_by_t_l',
        'signedByTl',
        'signed_by_tl2',
        'signedByTL2',
        'signed_by_t_l2',
        'signedByTl2',
        'signed_by_tl3',
        'signedByTL3',
        'signed_by_vve',
        'signedByVVE',
        'team_lead_for_action',
        'teamLeadForAction',
        'client_project_manager_signed_by',
        'clientProjectManagerSignedBy',
        'acknowledged_by',
        'acknowledgedBy',
        'acknowledged_by_tl',
        'acknowledgedByTL',
        'acknowledged_by_t_l',
        'acknowledgedByTl',
        'acknowledged_by_di',
        'acknowledgedByDI',
        'acknowledged_by_d_i',
        'acknowledgedByDi',
        'director_for_action',
        'directorForAction',
    ];

    private const TYPE_CONFIG = [
        'concept_design_review' => [
            'hasStage' => true,
            'modelClass' => ConceptDesignReview::class,
            'coreFieldMap' => [
                'form_type' => ['formType', 'form_type'],
                'project_id' => ['projectId', 'project_id'],
                'project_name' => ['projectName', 'project_name'],
                'project_number' => ['projectNumber', 'project_number', 'project_no'],
                'prepared_by' => ['preparedBy', 'prepared_by'],
                'discipline' => ['discipline'],
                'document_location' => ['documentLocation', 'document_location'],
                'review_method' => ['reviewMethod', 'review_method'],
                'reviewed_by' => ['reviewedBy', 'reviewed_by'],
                'reviewed_by_date' => ['reviewedByDate', 'reviewed_by_date'],
                'reviewed_by_status' => ['reviewedByStatus', 'reviewed_by_status'],
                'responded_by' => ['respondedBy', 'responded_by'],
                'responded_by_date' => ['respondedByDate', 'responded_by_date'],
                'responded_by_status' => ['respondedByStatus', 'responded_by_status'],
                'signed_by_tl' => ['signedByTL', 'signed_by_tl'],
                'signed_by_tl_date' => ['signedByTLDate', 'signed_by_tl_date'],
                'signed_by_tl_status' => ['signedByTLStatus', 'signed_by_tl_status'],
                'signed_by_tl2' => ['signedByTL2', 'signed_by_tl2'],
                'signed_by_tl2_date' => ['signedByTL2Date', 'signed_by_tl2_date'],
                'signed_by_tl2_status' => ['signedByTL2Status', 'signed_by_tl2_status'],
                'acknowledged_by' => ['acknowledgedBy', 'acknowledged_by'],
                'acknowledged_by_date' => ['acknowledgedByDate', 'acknowledged_by_date'],
                'acknowledged_by_status' => ['acknowledgedByStatus', 'acknowledged_by_status'],
                'status' => ['status'],
            ],
            'exactFilterMap' => [
                'form_type' => 'form_type',
                'discipline' => 'discipline',
                'review_method' => 'review_method',
                'reviewed_by' => 'reviewed_by',
                'reviewed_by_status' => 'reviewed_by_status',
                'responded_by' => 'responded_by',
                'responded_by_status' => 'responded_by_status',
                'signed_by_tl' => 'signed_by_tl',
                'signed_by_tl_status' => 'signed_by_tl_status',
                'signed_by_tl2' => 'signed_by_tl2',
                'signed_by_tl2_status' => 'signed_by_tl2_status',
                'acknowledged_by' => 'acknowledged_by',
                'acknowledged_by_status' => 'acknowledged_by_status',
                'status' => 'status',
            ],
            'likeFilterMap' => [
                'project_name' => 'project_name',
                'project_number' => 'project_number',
                'prepared_by' => 'prepared_by',
            ],
            'searchableColumns' => [
                'form_type',
                'project_name',
                'project_number',
                'prepared_by',
                'discipline',
                'reviewed_by',
                'reviewed_by_status',
                'responded_by',
                'responded_by_status',
                'signed_by_tl',
                'signed_by_tl_status',
                'signed_by_tl2',
                'signed_by_tl2_status',
                'acknowledged_by',
                'acknowledged_by_status',
                'status',
            ],
            'orderColumns' => [
                0 => 'id',
                1 => 'form_type',
                2 => 'project_name',
                3 => 'project_number',
                4 => 'prepared_by',
                5 => 'discipline',
                6 => 'reviewed_by',
                7 => 'responded_by',
                8 => 'status',
                9 => 'created_at',
            ],
        ],
        'schematic_design_review' => [
            'hasStage' => true,
            'modelClass' => SchematicDesignReview::class,
        ],
        'submission_review' => [
            'hasStage' => true,
            'modelClass' => SubmissionReview::class,
            'coreFieldMap' => [
                'form_type' => ['formType', 'form_type'],
                'project_id' => ['projectId', 'project_id'],
                'project_name' => ['projectName', 'project_name'],
                'project_number' => ['projectNumber', 'project_number', 'project_no'],
                'prepared_by' => ['preparedBy', 'prepared_by'],
                'discipline' => ['discipline'],
                'document_location' => ['documentLocation', 'document_location'],
                'review_method' => ['reviewMethod', 'review_method'],
                'reviewed_by' => ['reviewedBy', 'reviewed_by'],
                'reviewed_by_date' => ['reviewedByDate', 'reviewed_by_date'],
                'reviewed_by_status' => ['reviewedByStatus', 'reviewed_by_status'],
                'responded_by' => ['respondedBy', 'responded_by'],
                'responded_by_date' => ['respondedByDate', 'responded_by_date'],
                'responded_by_status' => ['respondedByStatus', 'responded_by_status'],
                'signed_by_tl' => ['signedByTL', 'signed_by_tl'],
                'signed_by_tl_date' => ['signedByTLDate', 'signed_by_tl_date'],
                'signed_by_tl_status' => ['signedByTLStatus', 'signed_by_tl_status'],
                'signed_by_tl2' => ['signedByTL2', 'signed_by_tl2'],
                'signed_by_tl2_date' => ['signedByTL2Date', 'signed_by_tl2_date'],
                'signed_by_tl2_status' => ['signedByTL2Status', 'signed_by_tl2_status'],
                'acknowledged_by' => ['acknowledgedBy', 'acknowledged_by'],
                'acknowledged_by_date' => ['acknowledgedByDate', 'acknowledged_by_date'],
                'acknowledged_by_status' => ['acknowledgedByStatus', 'acknowledged_by_status'],
                'status' => ['status'],
            ],
            'exactFilterMap' => [
                'form_type' => 'form_type',
                'discipline' => 'discipline',
                'review_method' => 'review_method',
                'reviewed_by' => 'reviewed_by',
                'reviewed_by_status' => 'reviewed_by_status',
                'responded_by' => 'responded_by',
                'responded_by_status' => 'responded_by_status',
                'signed_by_tl' => 'signed_by_tl',
                'signed_by_tl_status' => 'signed_by_tl_status',
                'signed_by_tl2' => 'signed_by_tl2',
                'signed_by_tl2_status' => 'signed_by_tl2_status',
                'acknowledged_by' => 'acknowledged_by',
                'acknowledged_by_status' => 'acknowledged_by_status',
                'status' => 'status',
            ],
            'likeFilterMap' => [
                'project_name' => 'project_name',
                'project_number' => 'project_number',
                'prepared_by' => 'prepared_by',
            ],
            'searchableColumns' => [
                'form_type',
                'project_name',
                'project_number',
                'prepared_by',
                'discipline',
                'reviewed_by',
                'reviewed_by_status',
                'responded_by',
                'responded_by_status',
                'signed_by_tl',
                'signed_by_tl_status',
                'signed_by_tl2',
                'signed_by_tl2_status',
                'acknowledged_by',
                'acknowledged_by_status',
                'status',
            ],
            'orderColumns' => [
                0 => 'id',
                1 => 'form_type',
                2 => 'project_name',
                3 => 'project_number',
                4 => 'prepared_by',
                5 => 'discipline',
                6 => 'reviewed_by',
                7 => 'responded_by',
                8 => 'status',
                9 => 'created_at',
            ],
        ],
        'tender_review' => [
            'modelClass' => TenderReview::class,
            'coreFieldMap' => [
                'form_type' => ['formType', 'form_type'],
                'project_id' => ['projectId', 'project_id'],
                'project_name' => ['projectName', 'project_name'],
                'project_number' => ['projectNumber', 'project_number', 'project_no'],
                'prepared_by' => ['preparedBy', 'prepared_by'],
                'discipline' => ['discipline'],
                'document_location' => ['documentLocation', 'document_location'],
                'review_method' => ['reviewMethod', 'review_method'],
                'reviewed_by' => ['reviewedBy', 'reviewed_by'],
                'reviewed_by_date' => ['reviewedByDate', 'reviewed_by_date'],
                'reviewed_by_status' => ['reviewedByStatus', 'reviewed_by_status'],
                'responded_by' => ['respondedBy', 'responded_by'],
                'responded_by_date' => ['respondedByDate', 'responded_by_date'],
                'responded_by_status' => ['respondedByStatus', 'responded_by_status'],
                'signed_by_vve' => ['signedByVVE', 'signed_by_vve'],
                'signed_by_vve_date' => ['signedByVVEDate', 'signed_by_vve_date'],
                'signed_by_vve_status' => ['signedByVVEStatus', 'signed_by_vve_status'],
                'signed_by_tl' => ['signedByTL', 'signed_by_tl'],
                'signed_by_tl_date' => ['signedByTLDate', 'signed_by_tl_date'],
                'signed_by_tl_status' => ['signedByTLStatus', 'signed_by_tl_status'],
                'acknowledged_by' => ['acknowledgedBy', 'acknowledged_by'],
                'acknowledged_by_date' => ['acknowledgedByDate', 'acknowledged_by_date'],
                'acknowledged_by_status' => ['acknowledgedByStatus', 'acknowledged_by_status'],
                'status' => ['status'],
            ],
            'exactFilterMap' => [
                'form_type' => 'form_type',
                'discipline' => 'discipline',
                'review_method' => 'review_method',
                'reviewed_by' => 'reviewed_by',
                'reviewed_by_status' => 'reviewed_by_status',
                'responded_by' => 'responded_by',
                'responded_by_status' => 'responded_by_status',
                'signed_by_vve' => 'signed_by_vve',
                'signed_by_vve_status' => 'signed_by_vve_status',
                'signed_by_tl' => 'signed_by_tl',
                'signed_by_tl_status' => 'signed_by_tl_status',
                'acknowledged_by' => 'acknowledged_by',
                'acknowledged_by_status' => 'acknowledged_by_status',
                'status' => 'status',
            ],
            'likeFilterMap' => [
                'project_name' => 'project_name',
                'project_number' => 'project_number',
                'prepared_by' => 'prepared_by',
            ],
            'searchableColumns' => [
                'form_type',
                'project_name',
                'project_number',
                'prepared_by',
                'discipline',
                'reviewed_by',
                'reviewed_by_status',
                'responded_by',
                'responded_by_status',
                'signed_by_vve',
                'signed_by_vve_status',
                'signed_by_tl',
                'signed_by_tl_status',
                'acknowledged_by',
                'acknowledged_by_status',
                'status',
            ],
            'orderColumns' => [
                0 => 'id',
                1 => 'form_type',
                2 => 'project_name',
                3 => 'project_number',
                4 => 'prepared_by',
                5 => 'discipline',
                6 => 'reviewed_by',
                7 => 'responded_by',
                8 => 'status',
                9 => 'created_at',
            ],
        ],
        'tender_csa_review' => [
            'hasStage' => true,
            'modelClass' => TenderCsaReview::class,
        ],
        'tender_csa_verification' => [
            'hasStage' => true,
            'modelClass' => TenderCsaVerification::class,
        ],
        'tender_mep_review' => [
            'hasStage' => true,
            'modelClass' => TenderMepReview::class,
        ],
        'tender_mep_verification' => [
            'hasStage' => true,
            'modelClass' => TenderMepVerification::class,
        ],
        'construction_validation' => [
            'hasStage' => true,
            'modelClass' => ConstructionValidation::class,
            'coreFieldMap' => [
                'form_type' => ['formType', 'form_type'],
                'project_id' => ['projectId', 'project_id'],
                'project_name' => ['projectName', 'project_name'],
                'project_number' => ['projectNumber', 'project_number', 'project_no'],
                'prepared_by' => ['preparedBy', 'prepared_by'],
                'discipline' => ['discipline'],
                'document_location' => ['documentLocation', 'document_location'],
                'completed_by' => ['completedBy', 'completed_by'],
                'completed_by_date' => ['completedByDate', 'completed_by_date'],
                'completed_by_status' => ['completedByStatus', 'completed_by_status'],
                'responded_by' => ['respondedBy', 'responded_by'],
                'responded_by_date' => ['respondedByDate', 'responded_by_date'],
                'responded_by_status' => ['respondedByStatus', 'responded_by_status'],
                'signed_by_tl' => ['signedByTL', 'signed_by_tl'],
                'signed_by_tl_date' => ['signedByTLDate', 'signed_by_tl_date'],
                'signed_by_tl_status' => ['signedByTLStatus', 'signed_by_tl_status'],
                'acknowledged_by_tl' => ['acknowledgedByTL', 'acknowledged_by_tl'],
                'acknowledged_by_tl_date' => ['acknowledgedByTLDate', 'acknowledged_by_tl_date'],
                'acknowledged_by_tl_status' => ['acknowledgedByTLStatus', 'acknowledged_by_tl_status'],
                'acknowledged_by_di' => ['acknowledgedByDI', 'acknowledged_by_di'],
                'acknowledged_by_di_date' => ['acknowledgedByDIDate', 'acknowledged_by_di_date'],
                'acknowledged_by_di_status' => ['acknowledgedByDIStatus', 'acknowledged_by_di_status'],
                'status' => ['status'],
            ],
            'exactFilterMap' => [
                'form_type' => 'form_type',
                'discipline' => 'discipline',
                'completed_by' => 'completed_by',
                'completed_by_status' => 'completed_by_status',
                'responded_by' => 'responded_by',
                'responded_by_status' => 'responded_by_status',
                'signed_by_tl' => 'signed_by_tl',
                'signed_by_tl_status' => 'signed_by_tl_status',
                'acknowledged_by_tl' => 'acknowledged_by_tl',
                'acknowledged_by_tl_status' => 'acknowledged_by_tl_status',
                'acknowledged_by_di' => 'acknowledged_by_di',
                'acknowledged_by_di_status' => 'acknowledged_by_di_status',
                'status' => 'status',
            ],
            'likeFilterMap' => [
                'project_name' => 'project_name',
                'project_number' => 'project_number',
                'prepared_by' => 'prepared_by',
            ],
            'searchableColumns' => [
                'form_type',
                'project_name',
                'project_number',
                'prepared_by',
                'discipline',
                'completed_by',
                'completed_by_status',
                'responded_by',
                'responded_by_status',
                'signed_by_tl',
                'signed_by_tl_status',
                'acknowledged_by_tl',
                'acknowledged_by_tl_status',
                'acknowledged_by_di',
                'acknowledged_by_di_status',
                'status',
            ],
            'orderColumns' => [
                0 => 'id',
                1 => 'form_type',
                2 => 'project_name',
                3 => 'project_number',
                4 => 'prepared_by',
                5 => 'discipline',
                6 => 'completed_by',
                7 => 'responded_by',
                8 => 'status',
                9 => 'created_at',
            ],
        ],
        'engineering_audit_review' => [
            'modelClass' => EngineeringAuditReview::class,
        ],
        'value_engineering_review' => [
            'modelClass' => ValueEngineeringReview::class,
        ],
        'leed_review' => [
            'modelClass' => LeedReview::class,
        ],
    ];

    public function getPage(Request $request)
    {
        $this->applyTypeConfig((string) $request->input('type', ''));

        return parent::getPage($request);
    }

    private function applyTypeConfig(string $type): void
    {
        $normalizedType = trim(strtolower($type));
        $config = self::TYPE_CONFIG[$normalizedType] ?? null;

        if (! $config) {
            throw new HttpException(422, 'Unsupported project review type');
        }

        $this->modelClass = $config['modelClass'];

        foreach (['coreFieldMap', 'exactFilterMap', 'likeFilterMap', 'searchableColumns', 'orderColumns'] as $property) {
            if (array_key_exists($property, $config)) {
                $this->{$property} = $config[$property];
            }
        }

        if (($config['hasStage'] ?? false) === true) {
            $this->enableStageField();
        }
    }

    private function enableStageField(): void
    {
        $this->coreFieldMap = $this->insertAssociativeAfter(
            $this->coreFieldMap,
            'project_number',
            'stage',
            ['stage']
        );
        $this->likeFilterMap = $this->insertAssociativeAfter(
            $this->likeFilterMap,
            'project_number',
            'stage',
            'stage'
        );
        $this->searchableColumns = $this->insertAfterValue(
            $this->searchableColumns,
            'project_number',
            'stage'
        );
    }

    protected function afterTransformRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $references = [];

        foreach ($rows as $row) {
            foreach (self::EMPLOYEE_REFERENCE_KEYS as $key) {
                $code = $this->normalizeEmployeeReference($row[$key] ?? null);
                if ($code !== null) {
                    $references[$code] = true;
                }
            }
        }

        if (empty($references)) {
            return $rows;
        }

        $employees = $this->loadEmployeeDisplayMap(array_keys($references));

        if (empty($employees)) {
            return $rows;
        }

        return array_map(function (array $row) use ($employees) {
            foreach (self::EMPLOYEE_REFERENCE_KEYS as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }

                $code = $this->normalizeEmployeeReference($row[$key] ?? null);
                if ($code === null || !isset($employees[$code])) {
                    continue;
                }

                $employee = $employees[$code];
                $row[$this->employeeNameKey($key)] = $employee['display_label'];
                $row[$this->employeeObjectKey($key)] = $employee;
            }

            return $row;
        }, $rows);
    }

    private function loadEmployeeDisplayMap(array $references): array
    {
        $references = array_values(array_unique(array_filter(array_map(function ($value) {
            return $this->normalizeEmployeeReference($value);
        }, $references))));

        if (empty($references)) {
            return [];
        }

        $numericIds = array_values(array_filter($references, function (string $value) {
            return preg_match('/^\d+$/', $value) === 1;
        }));

        $query = DB::table('employees')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($references, $numericIds) {
                $query->whereIn('code', $references);

                if (!empty($numericIds)) {
                    $query->orWhereIn('id', array_map('intval', $numericIds));
                }
            });

        $rows = $query
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
            ]);

        $map = [];

        foreach ($rows as $employee) {
            $payload = $this->employeePayload($employee);

            if (!empty($employee->code)) {
                $map[trim((string) $employee->code)] = $payload;
            }

            if (!empty($employee->id)) {
                $map[(string) $employee->id] = $payload;
            }
        }

        return $map;
    }

    private function employeePayload($employee): array
    {
        $initial = trim((string) ($employee->initial ?? ''));
        $firstname = trim((string) ($employee->firstname ?? ''));
        $lastname = trim((string) ($employee->lastname ?? ''));
        $name = trim($firstname . ' ' . $lastname);
        $displayLabel = trim(implode(', ', array_filter([$initial, $name], function ($value) {
            return trim((string) $value) !== '';
        })));

        if ($displayLabel === '') {
            $displayLabel = trim((string) ($employee->code ?? $employee->id ?? ''));
        }

        return [
            'id' => $employee->id,
            'code' => $employee->code,
            'initial' => $initial,
            'name' => $name,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email' => $employee->email,
            'level_name' => $employee->level_name,
            'title_name' => $employee->title_name,
            'department_name' => $employee->department_name,
            'employee_type_name' => $employee->employee_type_name,
            'display_label' => $displayLabel,
            'displayLabel' => $displayLabel,
        ];
    }

    private function normalizeEmployeeReference($value): ?string
    {
        if (is_array($value)) {
            foreach (['code', 'employee_code', 'employeeCode', 'id', 'employee_id', 'employeeId'] as $key) {
                if (array_key_exists($key, $value)) {
                    return $this->normalizeEmployeeReference($value[$key]);
                }
            }

            return null;
        }

        if (is_object($value)) {
            foreach (['code', 'employee_code', 'employeeCode', 'id', 'employee_id', 'employeeId'] as $key) {
                if (isset($value->{$key})) {
                    return $this->normalizeEmployeeReference($value->{$key});
                }
            }

            return null;
        }

        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' || $normalized === '-' ? null : $normalized;
    }

    private function employeeNameKey(string $key): string
    {
        return strpos($key, '_') !== false ? "{$key}_name" : "{$key}Name";
    }

    private function employeeObjectKey(string $key): string
    {
        return strpos($key, '_') !== false ? "{$key}_employee" : "{$key}Employee";
    }

    private function insertAssociativeAfter(array $items, string $afterKey, string $newKey, $newValue): array
    {
        if (array_key_exists($newKey, $items)) {
            return $items;
        }

        $result = [];

        foreach ($items as $key => $value) {
            $result[$key] = $value;

            if ($key === $afterKey) {
                $result[$newKey] = $newValue;
            }
        }

        if (! array_key_exists($newKey, $result)) {
            $result[$newKey] = $newValue;
        }

        return $result;
    }

    private function insertAfterValue(array $items, string $afterValue, string $newValue): array
    {
        if (in_array($newValue, $items, true)) {
            return $items;
        }

        $result = [];

        foreach ($items as $value) {
            $result[] = $value;

            if ($value === $afterValue) {
                $result[] = $newValue;
            }
        }

        if (! in_array($newValue, $result, true)) {
            $result[] = $newValue;
        }

        return $result;
    }
}
