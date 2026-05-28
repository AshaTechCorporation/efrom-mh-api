<?php
namespace App\Http\Controllers;

use App\Models\FeeSheet;
use App\Models\FeeSheetRevision;
use App\Models\ProposalProjectReference;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeSheetController extends Controller
{

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $projectReference = $this->resolveProposalProjectReference($request);
            if ($projectReference['response']) {
                return $projectReference['response'];
            }
            $projectReferenceId = $projectReference['id'];

            $feeSheet = FeeSheet::create([
                'mt_project_no'                   => $request->mt_project_no,
                'project_id'                      => $request->project_id,
                'proposal_project_reference_id'   => $projectReferenceId,
            ]);

            $revision = $feeSheet->revisions()->create(
                $this->revisionPayload($request, null, $projectReferenceId, $request->mt_project_no, 0, true)
            );

            $this->replaceRevisionChildren($revision, $request);

            // 5️⃣ Update current_revision_id
            $feeSheet->update([
                'current_revision_id' => $revision->id,
            ]);

            return response()->json([
                'fee_sheet_id' => $feeSheet->id,
                'revision_id'  => $revision->id,
                'revision_no'  => 0,
                'message'      => 'Fee Sheet created successfully',
            ]);
        });
    }

    public function update(Request $request, $id)
    {
        if (! is_numeric($id) || $id <= 0) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid ID provided',
            ], 400);
        }
        $request->validate([
            'mode' => 'required|in:edit_current,new_version',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $feeSheet = FeeSheet::with([
                'currentRevision.teamMembers',
                'currentRevision.feeAgreements',
                'currentRevision.jobCostings',
                'currentRevision.billingForecasts',
            ])->find($id);

            if (! $feeSheet) {
                return response()->json([
                    'status'  => false,
                    'message' => "Fee Sheet with ID {$id} not exist",
                ], 404);
            }

            $currentRevision = $feeSheet->currentRevision;
            $projectReference = $this->resolveProposalProjectReference(
                $request,
                $feeSheet->proposal_project_reference_id,
                $feeSheet->mt_project_no,
                $currentRevision->project_name ?? null
            );
            if ($projectReference['response']) {
                return $projectReference['response'];
            }
            $projectReferenceId = $projectReference['id'];

            if ($request->mode === 'edit_current') {

                $feeSheet->update([
                    'mt_project_no'                 => $request->mt_project_no ?? $feeSheet->mt_project_no,
                    'project_id'                    => $request->project_id ?? $feeSheet->project_id,
                    'proposal_project_reference_id' => $projectReferenceId ?? $feeSheet->proposal_project_reference_id,
                ]);

                $currentRevisionPayload = $this->revisionPayload(
                    $request,
                    $currentRevision,
                    $projectReferenceId ?? $currentRevision->proposal_project_reference_id,
                    $request->mt_project_no ?? $feeSheet->mt_project_no,
                    $currentRevision->rev_no,
                    true
                );
                $currentRevision->update($currentRevisionPayload);
                $revision = $currentRevision;
            } else {
                $currentRevision->update([
                    'is_latest' => false,
                ]);

                $revision = $feeSheet->revisions()->create(
                    $this->revisionPayload(
                        $request,
                        $currentRevision,
                        $projectReferenceId ?? $currentRevision->proposal_project_reference_id,
                        $request->mt_project_no ?? $feeSheet->mt_project_no,
                        $currentRevision->rev_no + 1,
                        true
                    )
                );

                $feeSheet->update([
                    'current_revision_id' => $revision->id,
                    'mt_project_no' => $request->mt_project_no ?? $feeSheet->mt_project_no,
                    'project_id' => $request->project_id ?? $feeSheet->project_id,
                    'proposal_project_reference_id' => $projectReferenceId ?? $feeSheet->proposal_project_reference_id,
                ]);
            }
            $this->replaceRevisionChildren(
                $revision,
                $request,
                $revision->is($currentRevision) ? null : $currentRevision
            );
            return response()->json([
                'fee_sheet_id' => $feeSheet->id,
                'revision_id'  => $revision->id,
                'revision_no'  => $revision->rev_no,
                'mode'         => $request->mode,
                'message'      => 'Fee Sheet updated successfully',
            ]);
        });
    }

    public function index(Request $request)
    {
        $request->validate([
            'fee_sheet_type'        => 'nullable|string|in:project,facade,lighting,transportation',
            'discipline_id'         => 'nullable|integer|exists:disciplines,id',
            'project_type_id'       => 'nullable|integer|exists:project_types,id',
            'director_in_charge_id' => 'nullable|integer|exists:users,id',
            'search'                => 'nullable|string|max:100',
            'date_from'             => 'nullable|date',
            'date_to'               => 'nullable|date|after_or_equal:date_from',
            'sort_by'               => 'nullable|string|in:created_at,updated_at,mt_project_no',
            'sort_direction'        => 'nullable|string|in:asc,desc',
            'per_page'              => 'nullable|integer|in:10,25,50,100',
        ]);

        $query = FeeSheet::with([
            'currentRevision.projectType',
            'currentRevision.discipline',
            'currentRevision.directorInCharge',
            'project',
        ]);

        $query->when($request->fee_sheet_type, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($qr) use ($request) {
                $qr->where('fee_sheet_type', $request->fee_sheet_type);
            });
        });

        $query->when($request->discipline_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($qr) use ($request) {
                $qr->where('discipline_id', $request->discipline_id);
            });
        });

        $query->when($request->project_type_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($qr) use ($request) {
                $qr->where('project_type_id', $request->project_type_id);
            });
        });

        $query->when($request->director_in_charge_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($qr) use ($request) {
                $qr->where('director_in_charge_id', $request->director_in_charge_id);
            });
        });

        $query->when($request->date_from, fn($q) =>
            $q->whereDate('created_at', '>=', $request->date_from)
        );

        $query->when($request->date_to, fn($q) =>
            $q->whereDate('created_at', '<=', $request->date_to)
        );

        $query->when($request->search, function ($q) use ($request) {
            $term = '%' . $request->search . '%';

            $q->where(function ($q) use ($term) {
                $q->where('mt_project_no', 'like', $term)
                    ->orWhereHas('currentRevision', function ($qr) use ($term) {
                        $qr->where('project_name', 'like', $term)
                            ->orWhere('client_name', 'like', $term)
                            ->orWhere('contact_name', 'like', $term)
                            ->orWhere('location', 'like', $term);
                    });
            });
        });

        $sortBy        = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage   = $request->input('per_page', 25);
        $feeSheets = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data'  => $feeSheets->items(),
            'meta'  => [
                'current_page' => $feeSheets->currentPage(),
                'per_page'     => $feeSheets->perPage(),
                'total'        => $feeSheets->total(),
                'last_page'    => $feeSheets->lastPage(),
                'from'         => $feeSheets->firstItem(),
                'to'           => $feeSheets->lastItem(),
            ],
            'links' => [
                'first' => $feeSheets->url(1),
                'last'  => $feeSheets->url($feeSheets->lastPage()),
                'prev'  => $feeSheets->previousPageUrl(),
                'next'  => $feeSheets->nextPageUrl(),
            ],
        ]);
    }

    public function page(Request $request)
    {
        $request->validate([
            'fee_sheet_type'        => 'nullable|string|in:project,facade,lighting,transportation',
            'discipline_id'         => 'nullable|integer|exists:disciplines,id',
            'project_type_id'       => 'nullable|integer|exists:project_types,id',
            'director_in_charge_id' => 'nullable|integer|exists:users,id',
            'search'                => 'nullable|string|max:100',
            'date_from'             => 'nullable|date',
            'date_to'               => 'nullable|date|after_or_equal:date_from',
            'sort_by'               => 'nullable|string|in:created_at,updated_at,project_name,mt_project_no,client_name',
            'sort_dir'              => 'nullable|string|in:asc,desc',
            'per_page'              => 'nullable|integer|in:10,25,50,100',
        ]);

        $query = FeeSheet::with('currentRevision');

        $query->when($request->fee_sheet_type, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($sub) use ($request) {
                $sub->where('fee_sheet_type', $request->fee_sheet_type);
            });
        });

        $query->when($request->discipline_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($sub) use ($request) {
                $sub->where('discipline_id', $request->discipline_id);
            });
        });

        $query->when($request->project_type_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($sub) use ($request) {
                $sub->where('project_type_id', $request->project_type_id);
            });
        });

        $query->when($request->director_in_charge_id, function ($q) use ($request) {
            $q->whereHas('currentRevision', function ($sub) use ($request) {
                $sub->where('director_in_charge_id', $request->director_in_charge_id);
            });
        });

        $query->when($request->date_from, fn($q) =>
            $q->whereDate('created_at', '>=', $request->date_from)
        );

        $query->when($request->date_to, fn($q) =>
            $q->whereDate('created_at', '<=', $request->date_to)
        );

        $query->when($request->search, function ($q) use ($request) {

            $term = '%' . $request->search . '%';

            $q->where(function ($main) use ($term) {

                // Search in FeeSheet table
                $main->where('mt_project_no', 'like', $term)

                // OR search in current revision table
                    ->orWhereHas('currentRevision', function ($sub) use ($term) {
                        $sub->where('project_name', 'like', $term)
                            ->orWhere('client_name', 'like', $term)
                            ->orWhere('contact_name', 'like', $term)
                            ->orWhere('location', 'like', $term);
                    });

            });
        });

        $query->orderBy(
            $request->input('sort_by', 'created_at'),
            $request->input('sort_dir', 'desc')
        );

        $data = $query
            ->with([
                'currentRevision.discipline',
                'currentRevision.projectType',
                'currentRevision.directorInCharge',
            ])
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return response()->json([
            'data'  => $data->items(),
            'meta'  => [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
                'from'         => $data->firstItem(),
                'to'           => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last'  => $data->url($data->lastPage()),
                'prev'  => $data->previousPageUrl(),
                'next'  => $data->nextPageUrl(),
            ],
        ]);
    }

    public function show($id)
    {
        if (! is_numeric($id) || $id <= 0) {
            return response()->json([
                'message' => 'Invalid ID provided.',
            ], 400);
        }

        $feeSheet = FeeSheet::with([
            'project',
            'projectReference',
            'currentRevision.projectType',
            'currentRevision.discipline',
            'currentRevision.directorInCharge',
            'currentRevision.projectReference',
            'currentRevision.teamMembers.employee',
            'currentRevision.feeAgreements',
            'currentRevision.jobCostings',
            'currentRevision.billingForecasts',
            'revisions.projectType',
            'revisions.discipline',
            'revisions.directorInCharge',
            'revisions.projectReference',
            'revisions.teamMembers.employee',
            'revisions.feeAgreements',
            'revisions.jobCostings',
            'revisions.billingForecasts',
        ])->find($id);

        if (! $feeSheet) {
            return response()->json([
                'message' => "Fee sheet with ID {$id} not found.",
            ], 404);
        }

        return response()->json([
            'data' => $this->serializeFeeSheet($feeSheet),
        ]);
    }

    public function destroy($id)
    {
        if (! is_numeric($id) || $id <= 0) {
            return response()->json([
                'message' => 'Invalid ID provided.',
            ], 400);
        }

        $feeSheet = FeeSheet::with('revisions')->find($id);

        if (! $feeSheet) {
            return response()->json([
                'message' => "Fee sheet with ID {$id} not found.",
            ], 404);
        }

        DB::transaction(function () use ($feeSheet) {

            foreach ($feeSheet->revisions as $revision) {

                $revision->teamMembers()->delete();
                $revision->feeAgreements()->delete();
                $revision->jobCostings()->delete();
                $revision->billingForecasts()->delete();

                $revision->delete();
            }

            $feeSheet->delete();
        });

        return response()->json([
            'message' => "Fee sheet with ID {$id} has been deleted.",
            'deleted_at' => $feeSheet->deleted_at,
        ]);
    }

    public function createRevision(Request $request, $feeSheetId)
    {
        return DB::transaction(function () use ($request, $feeSheetId) {

            $feeSheet = FeeSheet::with([
                'currentRevision.teamMembers',
                'currentRevision.feeAgreements',
                'currentRevision.jobCostings',
                'currentRevision.billingForecasts',
            ])->findOrFail($feeSheetId);

            $currentRevision = $feeSheet->currentRevision;
            $projectReference = $this->resolveProposalProjectReference(
                $request,
                $currentRevision->proposal_project_reference_id ?? $feeSheet->proposal_project_reference_id,
                $feeSheet->mt_project_no,
                $currentRevision->project_name ?? null
            );
            if ($projectReference['response']) {
                return $projectReference['response'];
            }
            $projectReferenceId = $projectReference['id'];

            $currentRevision->update([
                'is_latest' => false,
            ]);

            $newRevision = $feeSheet->revisions()->create(
                $this->revisionPayload(
                    $request,
                    $currentRevision,
                    $projectReferenceId ?? $currentRevision->proposal_project_reference_id,
                    $request->mt_project_no ?? $feeSheet->mt_project_no,
                    $currentRevision->rev_no + 1,
                    true
                )
            );

            $this->replaceRevisionChildren($newRevision, $request, $currentRevision);

            $feeSheet->update([
                'current_revision_id' => $newRevision->id,
                'mt_project_no' => $request->mt_project_no ?? $feeSheet->mt_project_no,
                'project_id' => $request->project_id ?? $feeSheet->project_id,
                'proposal_project_reference_id' => $projectReferenceId ?? $feeSheet->proposal_project_reference_id,
            ]);

            return response()->json([
                'fee_sheet_id'  => $feeSheet->id,
                'mt_project_no' => $feeSheet->mt_project_no,
                'revision_no'   => $newRevision->rev_no,
                'revision_id'   => $newRevision->id,
                'message'       => 'Revision created successfully',
            ]);
        });
    }

    public function revisions($feeSheetId)
    {
        if (! is_numeric($feeSheetId) || $feeSheetId <= 0) {
            return response()->json([
                'message' => 'Invalid Fee Sheet ID provided.',
            ], 400);
        }

        $feeSheet = FeeSheet::find($feeSheetId);

        if (! $feeSheet) {
            return response()->json([
                'message' => "Fee sheet with ID {$feeSheetId} not found.",
            ], 404);
        }

        $revisions = FeeSheetRevision::with([
            'projectType',
            'discipline',
            'directorInCharge',
            'projectReference',
            'teamMembers.employee',
            'feeAgreements',
            'jobCostings',
            'billingForecasts',
        ])
            ->where('fee_sheet_id', $feeSheetId)
            ->orderBy('rev_no')
            ->get()
            ->map(function ($revision) use ($feeSheet) {
                return $this->serializeRevision($revision, $feeSheet);
            });

        return response()->json([
            'data' => $revisions,
        ]);
    }

    public function getRevision($feeSheetId, $revisionNo)
    {
        $revision = FeeSheetRevision::with([
            'projectType',
            'discipline',
            'projectReference',
            'teamMembers.employee',
            'feeAgreements',
            'jobCostings',
            'billingForecasts',
            'directorInCharge',
        ])
            ->where('fee_sheet_id', $feeSheetId)
            ->where('rev_no', $revisionNo)
            ->firstOrFail();

        return response()->json([
            'data' => $this->serializeRevision($revision, $revision->feeSheet),
        ]);
    }

    private function revisionPayload(
        Request $request,
        ?FeeSheetRevision $fallback,
        $projectReferenceId,
        $mtProjectNo,
        int $revNo,
        bool $isLatest
    ): array {
        $isNewRevision = ! $fallback || (int) $fallback->rev_no !== $revNo;
        $statusDefault = $isNewRevision ? 'draft' : ($fallback->status ?? 'draft');

        return [
            'rev_no'                        => $revNo,
            'is_latest'                     => $isLatest,
            'fee_sheet_type'                => $this->inputOrFallback($request, 'fee_sheet_type', $fallback->fee_sheet_type ?? 'project'),
            'project_id'                    => $this->inputOrFallback($request, 'project_id', $fallback->project_id ?? null),
            'proposal_project_reference_id' => $projectReferenceId ?? $this->inputOrFallback($request, 'proposal_project_reference_id', $fallback->proposal_project_reference_id ?? null),
            'mt_project_no'                 => $mtProjectNo ?? $this->inputOrFallback($request, 'mt_project_no', $fallback->mt_project_no ?? null),
            'project_name'                  => $this->inputOrFallback($request, 'project_name', $fallback->project_name ?? null),
            'discipline_id'                 => $this->inputOrFallback($request, 'discipline_id', $fallback->discipline_id ?? null),
            'director_in_charge_id'         => $this->inputOrFallback($request, 'director_in_charge_id', $fallback->director_in_charge_id ?? null),
            'client_name'                   => $this->inputOrFallback($request, 'client_name', $fallback->client_name ?? null),
            'location'                      => $this->inputOrFallback($request, 'location', $fallback->location ?? null),
            'mtl_scope_detail'              => $this->inputOrFallback($request, 'mtl_scope_detail', $fallback->mtl_scope_detail ?? null),
            'contact_name'                  => $this->inputOrFallback($request, 'contact_name', $fallback->contact_name ?? null),
            'comment'                       => $this->inputOrFallback($request, 'comment', $fallback->comment ?? null),
            'status'                        => $this->inputOrFallback($request, 'status', $statusDefault),
            'project_type_id'               => $this->inputOrFallback($request, 'project_type_id', $fallback->project_type_id ?? null),
            'form_filled_by_id'             => $this->inputOrFallback($request, 'form_filled_by_id', $fallback->form_filled_by_id ?? null),
            'form_filled_by_date'           => $this->inputOrFallback($request, 'form_filled_by_date', $fallback->form_filled_by_date ?? null),
            'approved_by_ch_id'             => $this->inputOrFallback($request, 'approved_by_ch_id', $fallback->approved_by_ch_id ?? null),
            'approved_by_ch_date'           => $this->inputOrFallback($request, 'approved_by_ch_date', $fallback->approved_by_ch_date ?? null),
        ];
    }

    private function inputOrFallback(Request $request, string $key, $fallback = null)
    {
        return $request->exists($key) ? $request->input($key) : $fallback;
    }

    private function replaceRevisionChildren(
        FeeSheetRevision $revision,
        Request $request,
        ?FeeSheetRevision $fallback = null
    ): void {
        $fullPageRevision = $this->usesFullPageRevision($revision->fee_sheet_type);

        if ($request->exists('team')) {
            $revision->teamMembers()->delete();
            foreach ($this->normalizeTeamPayload($request->input('team')) as $employeeCode) {
                $revision->teamMembers()->create(['employee_code' => $employeeCode]);
            }
        } elseif ($fallback) {
            $revision->teamMembers()->delete();
            foreach ($fallback->teamMembers as $member) {
                if ($member->employee_code) {
                    $revision->teamMembers()->create(['employee_code' => $member->employee_code]);
                }
            }
        }

        if ($this->requestExistsAny($request, ['fee_agreements'])) {
            $revision->feeAgreements()->delete();
            $revision->feeAgreements()->createMany(
                $this->normalizeFeeAgreementRows($request->input('fee_agreements', []), $fullPageRevision)
            );
        } elseif ($fallback) {
            $revision->feeAgreements()->delete();
            $revision->feeAgreements()->createMany(
                $this->normalizeFeeAgreementRows($fallback->feeAgreements->toArray(), $fullPageRevision)
            );
        }

        if ($this->requestExistsAny($request, ['job_costing', 'job_costings'])) {
            $revision->jobCostings()->delete();
            $revision->jobCostings()->createMany(
                $this->normalizeJobCostingRows(
                    $this->requestInputAny($request, ['job_costing', 'job_costings'], []),
                    $fullPageRevision
                )
            );
        } elseif ($fallback) {
            $revision->jobCostings()->delete();
            $revision->jobCostings()->createMany(
                $this->normalizeJobCostingRows($fallback->jobCostings->toArray(), $fullPageRevision)
            );
        }

        if ($this->requestExistsAny($request, ['billing_forecast', 'billing_forecasts'])) {
            $revision->billingForecasts()->delete();
            $revision->billingForecasts()->createMany(
                $this->normalizeBillingForecastRows(
                    $this->requestInputAny($request, ['billing_forecast', 'billing_forecasts'], []),
                    $fullPageRevision
                )
            );
        } elseif ($fallback) {
            $revision->billingForecasts()->delete();
            $revision->billingForecasts()->createMany(
                $this->normalizeBillingForecastRows($fallback->billingForecasts->toArray(), $fullPageRevision)
            );
        }
    }

    private function normalizeTeamPayload($team): array
    {
        if (is_string($team)) {
            $team = explode(',', $team);
        }

        if (! is_array($team)) {
            return [];
        }

        return collect($team)
            ->map(function ($member) {
                if (is_array($member)) {
                    return $member['employee_code'] ?? ($member['code'] ?? null);
                }

                return trim((string) $member);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function requestExistsAny(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->exists($key)) {
                return true;
            }
        }

        return false;
    }

    private function requestInputAny(Request $request, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if ($request->exists($key)) {
                return $request->input($key);
            }
        }

        return $default;
    }

    private function usesFullPageRevision($feeSheetType): bool
    {
        return ($feeSheetType ?: 'project') === 'project';
    }

    private function latestSectionRows($rows): array
    {
        $rows = collect($rows ?? [])->values();
        if ($rows->isEmpty()) {
            return [];
        }

        $latestRevisionNo = $rows->max(function ($row) {
            return (int) ($row['revision_no'] ?? 0);
        });

        return $rows
            ->filter(function ($row) use ($latestRevisionNo) {
                return (int) ($row['revision_no'] ?? 0) === (int) $latestRevisionNo;
            })
            ->values()
            ->all();
    }

    private function normalizeFeeAgreementRows($rows, bool $fullPageRevision): array
    {
        $sourceRows = $fullPageRevision ? $this->latestSectionRows($rows) : collect($rows ?? [])->values()->all();

        return collect($sourceRows)
            ->map(function ($row, $index) use ($fullPageRevision) {
                $revisionNo = $fullPageRevision ? 0 : (int) ($row['revision_no'] ?? $index);
                $revisionLabel = $fullPageRevision
                    ? 'Original'
                    : ($row['revision_label'] ?? ($revisionNo === 0 ? 'Original' : "Rev {$revisionNo}"));

                return [
                    'revision_no'                => $revisionNo,
                    'revision_label'             => $revisionLabel,
                    'revision_name'              => $fullPageRevision ? 'Original' : ($row['revision_name'] ?? $revisionLabel),
                    'gross_fee_excl_vat'         => $row['gross_fee_excl_vat'] ?? 0,
                    'less_subconsultants_name'   => $row['less_subconsultants_name'] ?? null,
                    'less_subconsultants_number' => $row['less_subconsultants_number'] ?? 0,
                    'less_other_expenses'        => $row['less_other_expenses'] ?? 0,
                    'net_fee_excl_vat'           => $row['net_fee_excl_vat'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeJobCostingRows($rows, bool $fullPageRevision): array
    {
        $sourceRows = $fullPageRevision ? $this->latestSectionRows($rows) : collect($rows ?? [])->values()->all();

        return collect($sourceRows)
            ->map(function ($row, $index) use ($fullPageRevision) {
                $revisionNo = $fullPageRevision ? 0 : (int) ($row['revision_no'] ?? 0);

                return [
                    'revision_no'    => $revisionNo,
                    'revision_label' => $fullPageRevision
                        ? 'Original'
                        : ($row['revision_label'] ?? ($index === 0 ? 'Original' : "Rev {$revisionNo}")),
                    'phase'          => $row['phase'] ?? null,
                    'percent'        => $row['percent'] ?? ($row['percentage'] ?? 0),
                    'start_date'     => $row['start_date'] ?? null,
                    'end_date'       => $row['end_date'] ?? null,
                ];
            })
            ->filter(function ($row) {
                return ! empty($row['phase']);
            })
            ->values()
            ->all();
    }

    private function normalizeBillingForecastRows($rows, bool $fullPageRevision): array
    {
        $sourceRows = $fullPageRevision ? $this->latestSectionRows($rows) : collect($rows ?? [])->values()->all();

        return collect($sourceRows)
            ->map(function ($row, $index) use ($fullPageRevision) {
                $revisionNo = $fullPageRevision ? 0 : (int) ($row['revision_no'] ?? 0);

                return [
                    'revision_no'    => $revisionNo,
                    'revision_label' => $fullPageRevision
                        ? 'Original'
                        : ($row['revision_label'] ?? ($index === 0 ? 'Original' : "Rev {$revisionNo}")),
                    'month'          => $row['month'] ?? null,
                    'amount'         => $row['amount'] ?? 0,
                ];
            })
            ->filter(function ($row) {
                return ! empty($row['month']);
            })
            ->values()
            ->all();
    }

    private function serializeFeeSheet(FeeSheet $feeSheet): array
    {
        $data = $feeSheet->toArray();

        $revisions = $feeSheet->revisions
            ->sortBy('rev_no')
            ->values()
            ->map(function ($revision) use ($feeSheet) {
                return $this->serializeRevision($revision, $feeSheet);
            })
            ->values();

        $currentRevision = $feeSheet->currentRevision
            ? $this->serializeRevision($feeSheet->currentRevision, $feeSheet)
            : ($revisions->last() ?: null);

        $data['current_revision'] = $currentRevision;
        $data['revision'] = $currentRevision;
        $data['revisions'] = $revisions->all();

        return $data;
    }

    private function serializeRevision(FeeSheetRevision $revision, ?FeeSheet $feeSheet = null): array
    {
        $fullPageRevision = $this->usesFullPageRevision($revision->fee_sheet_type);
        $data = $revision->toArray();

        $feeAgreements = $this->normalizeFeeAgreementRows(
            $revision->feeAgreements->toArray(),
            $fullPageRevision
        );
        $jobCostings = $this->normalizeJobCostingRows(
            $revision->jobCostings->toArray(),
            $fullPageRevision
        );
        $billingForecasts = $this->normalizeBillingForecastRows(
            $revision->billingForecasts->toArray(),
            $fullPageRevision
        );

        $teamMembers = $revision->teamMembers
            ->map(function ($member) {
                return [
                    'id'            => $member->id,
                    'employee_code' => $member->employee_code,
                    'full_name'     => $member->employee->name ?? ($member->employee->full_name ?? null),
                ];
            })
            ->values()
            ->all();

        $data['revision_id'] = $revision->id;
        $data['revision_no'] = $revision->rev_no;
        $data['mt_project_no'] = $revision->mt_project_no ?? ($feeSheet->mt_project_no ?? null);
        $data['team_members'] = $teamMembers;
        $data['team'] = collect($teamMembers)->pluck('employee_code')->filter()->implode(', ');
        $data['fee_agreements'] = $feeAgreements;
        $data['job_costings'] = $jobCostings;
        $data['job_costing'] = $jobCostings;
        $data['billing_forecasts'] = $billingForecasts;
        $data['billing_forecast'] = $billingForecasts;

        return $data;
    }

    private function resolveProposalProjectReference(
        Request $request,
        $fallbackReferenceId = null,
        $fallbackProjectNumber = null,
        $fallbackProjectName = null
    ): array {
        $referenceId = $request->input('proposal_project_reference_id') ?? $fallbackReferenceId;
        $projectNumber = trim((string) ($request->input('mt_project_no') ?? $fallbackProjectNumber ?? ''));
        $projectName = trim((string) ($request->input('project_name') ?? $fallbackProjectName ?? ''));

        if (! $referenceId || $projectNumber === '') {
            return [
                'id' => $referenceId ?: null,
                'response' => null,
            ];
        }

        $baseReference = ProposalProjectReference::find($referenceId);
        if (! $baseReference) {
            return [
                'id' => $referenceId,
                'response' => null,
            ];
        }

        if ($this->normalizeProjectNumber($baseReference->project_number) === $this->normalizeProjectNumber($projectNumber)) {
            return [
                'id' => $baseReference->id,
                'response' => null,
            ];
        }

        $existingReference = ProposalProjectReference::withTrashed()
            ->whereRaw('LOWER(TRIM(project_number)) = ?', [$this->normalizeProjectNumber($projectNumber)])
            ->first();

        if ($existingReference) {
            return [
                'id' => null,
                'response' => $this->duplicateProposalProjectReferenceResponse($projectNumber, $existingReference),
            ];
        }

        try {
            $createdReference = ProposalProjectReference::create([
                'proposal_contract_review_id' => $baseReference->proposal_contract_review_id,
                'proposal_contract_review_project_id' => null,
                'proposal_number' => $baseReference->proposal_number,
                'project_number' => $projectNumber,
                'project_name' => $projectName !== '' ? $projectName : $baseReference->project_name,
                'status' => $baseReference->status ?: 'active',
                'metadata' => array_merge($baseReference->metadata ?? [], [
                    'source' => 'fee_sheet_manual_project_number',
                    'base_proposal_project_reference_id' => $baseReference->id,
                    'base_project_number' => $baseReference->project_number,
                ]),
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return [
                'id' => null,
                'response' => $this->duplicateProposalProjectReferenceResponse($projectNumber),
            ];
        }

        return [
            'id' => $createdReference->id,
            'response' => null,
        ];
    }

    private function normalizeProjectNumber($projectNumber): string
    {
        return strtolower(trim((string) $projectNumber));
    }

    private function duplicateProposalProjectReferenceResponse(string $projectNumber, ?ProposalProjectReference $existingReference = null)
    {
        return response()->json([
            'status' => false,
            'code' => 'DUPLICATE_PROPOSAL_PROJECT_REFERENCE',
            'message' => "MT Project No. {$projectNumber} already exists in proposal project references.",
            'data' => [
                'project_number' => $projectNumber,
                'existing_reference_id' => $existingReference->id ?? null,
                'existing_project_name' => $existingReference->project_name ?? null,
                'is_deleted_reference' => $existingReference ? (bool) $existingReference->deleted_at : false,
            ],
        ], 422);
    }

}
