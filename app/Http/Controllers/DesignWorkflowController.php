<?php

namespace App\Http\Controllers;

use App\Models\ConceptDesignReview;
use App\Models\ConstructionValidation;
use App\Models\SchematicDesignReview;
use App\Models\SubmissionReview;
use App\Models\TenderReview;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DesignWorkflowController extends Controller
{
    private const OVERDUE_GRACE_DAYS = 5;

    private const TYPE_CONFIG = [
        'concept_design_review' => [
            'label' => 'Concept Design Review',
            'modelClass' => ConceptDesignReview::class,
            'route' => '/concept-design-review/view',
            'steps' => 'standard',
        ],
        'schematic_design_review' => [
            'label' => 'Schematic Design Review',
            'modelClass' => SchematicDesignReview::class,
            'route' => '/schematic-design-review/view',
            'steps' => 'standard',
        ],
        'submission_review' => [
            'label' => 'Submission Review',
            'modelClass' => SubmissionReview::class,
            'route' => '/submission-review/view',
            'steps' => 'standard',
        ],
        'tender_review' => [
            'label' => 'Tender Review',
            'modelClass' => TenderReview::class,
            'route' => '/tender-review/view',
            'steps' => 'tender',
        ],
        'construction_validation' => [
            'label' => 'Construction Validation',
            'modelClass' => ConstructionValidation::class,
            'route' => '/construction-validation/view',
            'steps' => 'construction',
        ],
    ];

    public function documentTypes()
    {
        $types = collect(self::TYPE_CONFIG)->map(function (array $config, string $type) {
            return [
                'type' => $type,
                'label' => $config['label'],
            ];
        })->values();

        return $this->returnSuccess('success', $types);
    }

    public function documents(Request $request)
    {
        $config = $this->resolveConfig((string) $request->query('type'));

        if (! $config) {
            return $this->returnErrorData('Unsupported design review type', 422);
        }

        $search = trim((string) $request->query('search', ''));
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 500));

        $query = $this->newQuery($config);

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('project_name', 'like', '%' . $search . '%')
                    ->orWhere('project_number', 'like', '%' . $search . '%')
                    ->orWhere('discipline', 'like', '%' . $search . '%')
                    ->orWhere('document_location', 'like', '%' . $search . '%');
            });
        }

        $documents = $query
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (Model $item) => $this->buildDocumentOption($item, $config))
            ->values();

        return $this->returnSuccess('success', $documents);
    }

    public function show(string $type, $id)
    {
        $config = $this->resolveConfig($type);

        if (! $config) {
            return $this->returnErrorData('Unsupported design review type', 422);
        }

        $item = $this->newQuery($config)->where('id', $id)->first();

        if (! $item) {
            return $this->returnErrorData('ไม่พบข้อมูล', 404);
        }

        $payload = $this->payload($item);
        $document = $this->buildDocumentOption($item, $config);
        $steps = $this->buildWorkflowSteps($item, $payload, $config);
        $completedCount = collect($steps)->where('status', 'complete')->count();
        $currentStep = collect($steps)->first(fn (array $step) => in_array($step['status'], ['in_process', 'overdue', 'rejected'], true));

        return $this->returnSuccess('success', [
            'type' => $type,
            'typeLabel' => $config['label'],
            'document' => $document,
            'summary' => [
                'totalSteps' => count($steps),
                'completedSteps' => $completedCount,
                'progressPercent' => count($steps) > 0 ? (int) round(($completedCount / count($steps)) * 100) : 0,
                'currentStep' => $currentStep,
                'isComplete' => $completedCount === count($steps),
            ],
            'steps' => $steps,
        ]);
    }

    public function report(Request $request)
    {
        $limit = (int) $request->query('limit', 1000);
        $limit = max(1, min($limit, 5000));
        $rows = collect();

        foreach (self::TYPE_CONFIG as $type => $config) {
            $this->newQuery($config)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->each(function (Model $item) use ($type, $config, $rows) {
                    $payload = $this->payload($item);
                    $steps = $this->buildWorkflowSteps($item, $payload, $config);
                    $currentStep = collect($steps)->first(fn (array $step) => in_array($step['status'], ['in_process', 'overdue', 'rejected'], true));

                    if (! $currentStep || $currentStep['status'] !== 'overdue') {
                        return;
                    }

                    $document = $this->buildDocumentOption($item, $config);

                    $rows->push([
                        'type' => $type,
                        'typeLabel' => $config['label'],
                        'document' => $document,
                        'currentStep' => $currentStep,
                        'responsiblePerson' => $currentStep['assignee'],
                        'projectNumber' => $document['projectNumber'],
                        'projectName' => $document['projectName'],
                        'discipline' => $document['discipline'],
                        'stage' => strtoupper($config['label']),
                        'statusLabel' => $this->reportStatusLabel($currentStep),
                        'days' => $currentStep['overdueDays'],
                        'totalWaitingDays' => $currentStep['waitingDays'],
                        'lastStatus' => 'Active',
                    ]);
                });
        }

        return $this->returnSuccess('success', [
            'generatedAt' => Carbon::now()->toDateTimeString(),
            'rows' => $rows
                ->sortByDesc('days')
                ->values(),
        ]);
    }

    private function resolveConfig(string $type): ?array
    {
        $normalized = trim(strtolower($type));

        return self::TYPE_CONFIG[$normalized] ?? null;
    }

    private function newQuery(array $config)
    {
        $class = $config['modelClass'];

        return $class::query()->whereNull('deleted_at');
    }

    private function buildDocumentOption(Model $item, array $config): array
    {
        $payload = $this->payload($item);
        $projectNumber = $this->pick($item, $payload, ['project_number', 'projectNumber', 'project_no']) ?: '-';
        $projectName = $this->pick($item, $payload, ['project_name', 'projectName', 'name']) ?: '-';
        $discipline = $this->pick($item, $payload, ['discipline', 'disciplineOther', 'discipline_other']) ?: '-';

        return [
            'id' => $item->id,
            'label' => trim($projectNumber . ' | ' . $projectName . ' | ' . $discipline),
            'projectNumber' => $projectNumber,
            'projectName' => $projectName,
            'discipline' => $discipline,
            'documentLocation' => $this->pick($item, $payload, ['document_location', 'documentLocation']) ?: '',
            'documentTypes' => $this->pickArray($item, $payload, ['documentTypes', 'document_types', 'documentsReviewed', 'documents_reviewed']),
            'createdAt' => $item->created_at,
            'updatedAt' => $item->updated_at,
            'viewRoute' => $config['route'] . '/' . $item->id,
        ];
    }

    private function buildWorkflowSteps(Model $item, array $payload, array $config): array
    {
        switch ($config['steps']) {
            case 'tender':
                $definitions = $this->tenderSteps();
                break;
            case 'construction':
                $definitions = $this->constructionSteps();
                break;
            default:
                $definitions = $this->standardSteps();
                break;
        }

        $steps = [];
        $foundCurrent = false;
        $previousCompletedAt = null;

        foreach ($definitions as $index => $definition) {
            $statusValue = $this->pick($item, $payload, $definition['statusKeys'] ?? []);
            $dateValue = $this->pick($item, $payload, $definition['dateKeys'] ?? []);
            $assignee = $this->pick($item, $payload, $definition['assigneeKeys'] ?? []);
            $isRejected = $this->isRejectedStatus($statusValue);
            $isComplete = $this->isCompleteStatus($statusValue) || $dateValue !== null;
            $status = 'pending';

            if ($index === 0) {
                $isComplete = true;
                $dateValue = $dateValue ?: $item->created_at;
            }

            if ($isRejected) {
                $status = 'rejected';
                $foundCurrent = true;
            } elseif ($isComplete) {
                $status = 'complete';
            } elseif (! $foundCurrent) {
                $status = 'in_process';
                $foundCurrent = true;
            }

            $receivedAt = $index === 0 ? $dateValue : $previousCompletedAt;
            $waitingDays = $this->workflowWaitingDays($receivedAt, $dateValue, $status);
            $overdueDays = 0;

            if ($status === 'in_process' && $waitingDays >= self::OVERDUE_GRACE_DAYS) {
                $status = 'overdue';
                $overdueDays = $waitingDays - self::OVERDUE_GRACE_DAYS + 1;
            }

            $steps[] = [
                'key' => $definition['key'],
                'role' => $definition['role'],
                'action' => $definition['action'],
                'assignee' => $assignee ?: '-',
                'date' => $dateValue,
                'receivedAt' => $receivedAt,
                'waitingDays' => $waitingDays,
                'overdueDays' => $overdueDays,
                'isOverdue' => $status === 'overdue',
                'status' => $status,
                'statusText' => $this->statusText($statusValue, $status),
                'order' => $index + 1,
            ];

            if ($status === 'complete') {
                $previousCompletedAt = $dateValue ?: $previousCompletedAt;
            }
        }

        return $steps;
    }

    private function standardSteps(): array
    {
        return [
            [
                'key' => 'created',
                'role' => 'TE',
                'action' => 'Create document',
                'assigneeKeys' => ['prepared_by', 'preparedBy', 'create_by'],
                'dateKeys' => ['created_at', 'createdAt'],
            ],
            [
                'key' => 'reviewed',
                'role' => 'Reviewer',
                'action' => 'Review',
                'assigneeKeys' => ['reviewed_by', 'reviewedBy', 'reviewer_for_action', 'reviewerForAction'],
                'dateKeys' => ['reviewed_by_date', 'reviewedByDate', 'reviewed_by_tl_date', 'reviewed_by_t_l_date'],
                'statusKeys' => ['reviewed_by_status', 'reviewedByStatus', 'reviewed_by_tl_status', 'reviewed_by_t_l_status'],
            ],
            [
                'key' => 'responded',
                'role' => 'TE',
                'action' => 'Respond',
                'assigneeKeys' => ['responded_by', 'respondedBy', 'prepared_by', 'preparedBy'],
                'dateKeys' => ['responded_by_date', 'respondedByDate'],
                'statusKeys' => ['responded_by_status', 'respondedByStatus'],
            ],
            [
                'key' => 'signed_tl',
                'role' => 'Team Lead',
                'action' => 'Review',
                'assigneeKeys' => ['signed_by_tl', 'signedByTL', 'signed_by_t_l', 'team_lead_for_action', 'teamLeadForAction'],
                'dateKeys' => ['signed_by_tl_date', 'signedByTLDate', 'signed_by_t_l_date'],
                'statusKeys' => ['signed_by_tl_status', 'signedByTLStatus', 'signed_by_t_l_status'],
            ],
            [
                'key' => 'signed_tl2',
                'role' => 'Reviewer',
                'action' => 'Approve',
                'assigneeKeys' => ['signed_by_tl2', 'signedByTL2', 'signed_by_t_l2'],
                'dateKeys' => ['signed_by_tl2_date', 'signedByTL2Date', 'signed_by_t_l2_date'],
                'statusKeys' => ['signed_by_tl2_status', 'signedByTL2Status', 'signed_by_t_l2_status'],
            ],
            [
                'key' => 'acknowledged',
                'role' => 'Director',
                'action' => 'Acknowledge',
                'assigneeKeys' => ['acknowledged_by', 'acknowledgedBy', 'acknowledged_by_d_i', 'director_for_action', 'directorForAction'],
                'dateKeys' => ['acknowledged_by_date', 'acknowledgedByDate', 'acknowledged_by_d_i_date'],
                'statusKeys' => ['acknowledged_by_status', 'acknowledgedByStatus', 'acknowledged_by_d_i_status'],
            ],
        ];
    }

    private function tenderSteps(): array
    {
        $steps = $this->standardSteps();
        array_splice($steps, 3, 0, [[
            'key' => 'signed_vve',
            'role' => 'VVE',
            'action' => 'Sign',
            'assigneeKeys' => ['signed_by_vve', 'signedByVVE', 'signed_by_v_v_e'],
            'dateKeys' => ['signed_by_vve_date', 'signedByVVEDate', 'signed_by_v_v_e_date'],
            'statusKeys' => ['signed_by_vve_status', 'signedByVVEStatus', 'signed_by_v_v_e_status'],
        ]]);

        return array_values($steps);
    }

    private function constructionSteps(): array
    {
        return [
            [
                'key' => 'created',
                'role' => 'TE',
                'action' => 'Create document',
                'assigneeKeys' => ['prepared_by', 'preparedBy', 'create_by'],
                'dateKeys' => ['created_at', 'createdAt'],
            ],
            [
                'key' => 'completed',
                'role' => 'Reviewer',
                'action' => 'Complete checklist',
                'assigneeKeys' => ['completed_by', 'completedBy', 'prepared_by', 'preparedBy'],
                'dateKeys' => ['completed_by_date', 'completedByDate'],
                'statusKeys' => ['completed_by_status', 'completedByStatus', 'completed_by_t_l_status'],
            ],
            [
                'key' => 'responded',
                'role' => 'TE',
                'action' => 'Respond',
                'assigneeKeys' => ['responded_by', 'respondedBy', 'prepared_by', 'preparedBy'],
                'dateKeys' => ['responded_by_date', 'respondedByDate'],
                'statusKeys' => ['responded_by_status', 'respondedByStatus'],
            ],
            [
                'key' => 'signed_tl',
                'role' => 'Team Lead',
                'action' => 'Review',
                'assigneeKeys' => ['signed_by_tl', 'signedByTL', 'signed_by_t_l', 'team_lead_for_action', 'teamLeadForAction'],
                'dateKeys' => ['signed_by_tl_date', 'signedByTLDate', 'signed_by_t_l_date'],
                'statusKeys' => ['signed_by_tl_status', 'signedByTLStatus', 'signed_by_t_l_status'],
            ],
            [
                'key' => 'acknowledged_tl',
                'role' => 'Team Lead',
                'action' => 'Acknowledge',
                'assigneeKeys' => ['acknowledged_by_tl', 'acknowledgedByTL', 'acknowledged_by_t_l'],
                'dateKeys' => ['acknowledged_by_tl_date', 'acknowledgedByTLDate', 'acknowledged_by_t_l_date'],
                'statusKeys' => ['acknowledged_by_tl_status', 'acknowledgedByTLStatus', 'acknowledged_by_t_l_status'],
            ],
            [
                'key' => 'acknowledged_di',
                'role' => 'Director',
                'action' => 'Acknowledge',
                'assigneeKeys' => ['acknowledged_by_di', 'acknowledgedByDI', 'acknowledged_by_d_i', 'director_for_action', 'directorForAction'],
                'dateKeys' => ['acknowledged_by_di_date', 'acknowledgedByDIDate', 'acknowledged_by_d_i_date'],
                'statusKeys' => ['acknowledged_by_di_status', 'acknowledgedByDIStatus', 'acknowledged_by_d_i_status'],
            ],
        ];
    }

    private function payload(Model $item): array
    {
        $payload = json_decode($item->payload ?? '[]', true);

        return is_array($payload) ? $payload : [];
    }

    private function pick(Model $item, array $payload, array $keys)
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? $item->{$key} ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function pickArray(Model $item, array $payload, array $keys): array
    {
        $value = $this->pick($item, $payload, $keys);

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($entry) => $entry !== null && $entry !== ''));
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return [];
            }

            if (substr($trimmed, 0, 1) === '[') {
                $decoded = json_decode($trimmed, true);

                if (is_array($decoded)) {
                    return array_values(array_filter($decoded, fn ($entry) => $entry !== null && $entry !== ''));
                }
            }

            return array_values(array_filter(array_map('trim', explode(',', $trimmed))));
        }

        return [];
    }

    private function isCompleteStatus($status): bool
    {
        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, ['approved', 'approve', 'signed', 'acknowledged', 'completed', 'complete', 'done'], true);
    }

    private function isRejectedStatus($status): bool
    {
        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, ['rejected', 'reject', 'declined', 'decline'], true);
    }

    private function statusText($rawStatus, string $status): string
    {
        if ($rawStatus !== null && trim((string) $rawStatus) !== '') {
            return (string) $rawStatus;
        }

        switch ($status) {
            case 'complete':
                return 'Complete';
            case 'in_process':
                return 'On process';
            case 'overdue':
                return 'Overdue';
            case 'rejected':
                return 'Rejected';
            default:
                return 'Pending';
        }
    }

    private function reportStatusLabel(array $step): string
    {
        $role = strtolower((string) ($step['role'] ?? ''));
        $action = strtolower((string) ($step['action'] ?? 'action'));

        if (strpos($role, 'director') !== false) {
            $roleLabel = 'DI';
        } elseif (strpos($role, 'team lead') !== false) {
            $roleLabel = 'TL';
        } elseif ($role === 'te') {
            $roleLabel = 'TE';
        } else {
            $roleLabel = (string) ($step['role'] ?? '-');
        }

        return trim($roleLabel . ' to ' . $action);
    }

    private function workflowWaitingDays($receivedAt, $completedAt, string $status): int
    {
        if ($receivedAt === null || $status === 'pending') {
            return 0;
        }

        $start = $this->parseWorkflowDate($receivedAt);

        if (! $start) {
            return 0;
        }

        $end = in_array($status, ['complete', 'rejected'], true)
            ? $this->parseWorkflowDate($completedAt)
            : Carbon::now();

        if (! $end) {
            $end = Carbon::now();
        }

        return max(0, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
    }

    private function parseWorkflowDate($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
