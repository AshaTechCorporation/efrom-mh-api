<?php
namespace App\Http\Controllers;

use App\Models\FeeSheet;
use App\Models\FeeSheetRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeSheetController extends Controller
{

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $feeSheet = FeeSheet::create([
                'mt_project_no' => $request->mt_project_no,
                'project_id'    => $request->project_id,
            ]);

            $revision = $feeSheet->revisions()->create([
                'rev_no'                => 0,
                'is_latest'             => true,

                'fee_sheet_type'        => $request->fee_sheet_type,
                'project_id'            => $request->project_id,
                'project_name'          => $request->project_name,
                'discipline_id'         => $request->discipline_id,
                'director_in_charge_id' => $request->director_in_charge_id,
                'client_name'           => $request->client_name,
                'location'              => $request->location,
                'mtl_scope_detail'      => $request->mtl_scope_detail,
                'contact_name'          => $request->contact_name,
                'comment'               => $request->comment,

                'project_type_id'       => $request->project_type_id,
                'form_filled_by_id'     => $request->form_filled_by_id,
                'form_filled_by_date'   => $request->form_filled_by_date,
                'approved_by_ch_id'     => $request->approved_by_ch_id,
                'approved_by_ch_date'   => $request->approved_by_ch_date,
            ]);

            if ($request->team) {
                $revision->teamMembers()->create([
                    'employee_code' => $request->team,
                ]);
            }

            $revision->feeAgreements()
                ->createMany($request->fee_agreements ?? []);

            $revision->jobCostings()
                ->createMany($request->job_costing ?? []);

            $revision->billingForecasts()
                ->createMany($request->billing_forecast ?? []);

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
            'currentRevision.projectType',
            'currentRevision.discipline',
            'currentRevision.directorInCharge',
            'currentRevision.teamMembers',
            'currentRevision.feeAgreements',
            'currentRevision.jobCostings',
            'currentRevision.billingForecasts',
        ])->find($id);

        if (! $feeSheet) {
            return response()->json([
                'message' => "Fee sheet with ID {$id} not found.",
            ], 404);
        }

        return response()->json([
            'data' => $feeSheet,
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

            $feeSheet = FeeSheet::with('currentRevision')->findOrFail($feeSheetId);

            $currentRevision = $feeSheet->currentRevision;

            $currentRevision->update([
                'is_latest' => false,
            ]);

            $newRevision = $feeSheet->revisions()->create([
                'rev_no'                => $currentRevision->rev_no + 1,
                'is_latest'             => true,

                'fee_sheet_type'        => $request->fee_sheet_type ?? $currentRevision->fee_sheet_type,
                'project_id'            => $request->project_id ?? $currentRevision->project_id,
                'project_name'          => $request->project_name ?? $currentRevision->project_name,
                'discipline_id'         => $request->discipline_id ?? $currentRevision->discipline_id,
                'director_in_charge_id' => $request->director_in_charge_id ?? $currentRevision->director_in_charge_id,
                'client_name'           => $request->client_name ?? $currentRevision->client_name,
                'location'              => $request->location ?? $currentRevision->location,
                'mtl_scope_detail'      => $request->mtl_scope_detail ?? $currentRevision->mtl_scope_detail,
                'contact_name'          => $request->contact_name ?? $currentRevision->contact_name,
                'comment'               => $request->comment ?? $currentRevision->comment,

                'project_type_id'       => $request->project_type_id ?? $currentRevision->project_type_id,
                'form_filled_by_id'     => $request->form_filled_by_id ?? $currentRevision->form_filled_by_id,
                'form_filled_by_date'   => $request->form_filled_by_date ?? $currentRevision->form_filled_by_date,
                'approved_by_ch_id'     => $request->approved_by_ch_id ?? $currentRevision->approved_by_ch_id,
                'approved_by_ch_date'   => $request->approved_by_ch_date ?? $currentRevision->approved_by_ch_date,
            ]);

            foreach ($currentRevision->teamMembers as $member) {
                $newRevision->teamMembers()->create([
                    'employee_code' => $member->employee_code,
                ]);
            }

            foreach ($currentRevision->feeAgreements as $agreement) {
                $newRevision->feeAgreements()->create(
                    $agreement->only([
                        'gross_fee_excl_vat',
                        'less_subconsultants_name',
                        'less_subconsultants_number',
                        'less_other_expenses',
                        'net_fee_excl_vat',
                    ])
                );
            }

            foreach ($currentRevision->jobCostings as $costing) {
                $newRevision->jobCostings()->create(
                    $costing->only([
                        'phase',
                        'percent',
                        'start_date',
                        'end_date',
                    ])
                );
            }

            foreach ($currentRevision->billingForecasts as $forecast) {
                $newRevision->billingForecasts()->create(
                    $forecast->only([
                        'month',
                        'amount',
                    ])
                );
            }

            $feeSheet->update([
                'current_revision_id' => $newRevision->id,
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

        $revisions = FeeSheetRevision::with('directorInCharge')
            ->where('fee_sheet_id', $feeSheetId)
            ->orderByDesc('rev_no')
            ->get()
            ->map(function ($revision) {
                return [
                    'revision_id' => $revision->id,
                    'revision_no' => $revision->rev_no,
                    'is_latest'   => (bool) $revision->is_latest,
                    'created_at'  => $revision->created_at,
                    'created_by'  => $revision->directorInCharge ? [
                        'id'        => $revision->directorInCharge->id,
                        'full_name' => $revision->directorInCharge->name,
                    ] : null,
                ];
            });

        return response()->json($revisions);
    }

    public function getRevision($feeSheetId, $revisionNo)
    {
        $revision = FeeSheetRevision::with([
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
            'fee_sheet_type'        => $revision->fee_sheet_type,
            'fee_sheet_id'          => $revision->fee_sheet_id,
            'mt_project_no'         => $revision->feeSheet->mt_project_no,
            'revision_no'           => $revision->rev_no,
            'project_name'          => $revision->project_name,
            'discipline_id'         => $revision->discipline_id,
            'director_in_charge_id' => $revision->director_in_charge_id,
            'client_name'           => $revision->client_name,
            'location'              => $revision->location,
            'mtl_scope_detail'      => $revision->mtl_scope_detail,
            'contact_name'          => $revision->contact_name,
            'comment'               => $revision->comment,

            'team'                  => $revision->teamMembers->map(function ($member) {
                return [
                    'id'        => $member->id,
                    'full_name' => $member->employee->full_name ?? null,
                ];
            }),

            'fee_agreements'        => $revision->feeAgreements->map(function ($row, $index) {
                return [
                    'revision_index'             => $index,
                    'gross_fee_excl_vat'         => $row->gross_fee_excl_vat,
                    'less_subconsultants_name'   => $row->less_subconsultants_name,
                    'less_subconsultants_number' => $row->less_subconsultants_number,
                    'less_other_expenses'        => $row->less_other_expenses,
                    'net_fee_excl_vat'           => $row->net_fee_excl_vat,
                ];
            }),

            'job_costing'           => $revision->jobCostings->map(function ($row) {
                return [
                    'phase'      => $row->phase,
                    'percent'    => $row->percent,
                    'start_date' => $row->start_date,
                    'end_date'   => $row->end_date,
                ];
            }),

            'billing_forecast'      => $revision->billingForecasts->map(function ($row) {
                return [
                    'month'  => $row->month,
                    'amount' => $row->amount,
                ];
            }),
        ]);
    }

}
