<?php
namespace App\Http\Controllers;

use App\Models\FeeSheet;
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

        $query = FeeSheet::query();

        $query->when($request->fee_sheet_type, fn($q) =>
            $q->where('fee_sheet_type', $request->fee_sheet_type)
        );

        $query->when($request->discipline_id, fn($q) =>
            $q->where('discipline_id', $request->discipline_id)
        );

        $query->when($request->project_type_id, fn($q) =>
            $q->where('project_type_id', $request->project_type_id)
        );

        $query->when($request->director_in_charge_id, fn($q) =>
            $q->where('director_in_charge_id', $request->director_in_charge_id)
        );

        $query->when($request->date_from, fn($q) =>
            $q->whereDate('created_at', '>=', $request->date_from)
        );

        $query->when($request->date_to, fn($q) =>
            $q->whereDate('created_at', '<=', $request->date_to)
        );

        $query->when($request->search, function ($q) use ($request) {
            $term = '%' . $request->search . '%';
            $q->where(function ($q) use ($term) {
                $q->where('project_name', 'like', $term)
                    ->orWhere('mt_project_no', 'like', $term)
                    ->orWhere('client_name', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('location', 'like', $term);
            });
        });

        $query->orderBy(
            $request->input('sort_by', 'created_at'),
            $request->input('sort_dir', 'desc')
        );

        $data = $query
            ->with([
                'project',
                'discipline',
                'projectType',
                'directorInCharge',
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

    public function createRevision($feeSheetId)
    {
        return DB::transaction(function () use ($feeSheetId) {

            $feeSheet = FeeSheet::with('currentRevision')->findOrFail($feeSheetId);

            $currentRevision = $feeSheet->currentRevision;

            $currentRevision->update([
                'is_latest' => false,
            ]);

            $newRevision = $feeSheet->revisions()->create([
                'rev_no'                => $currentRevision->rev_no + 1,
                'is_latest'             => true,

                'fee_sheet_type'        => $currentRevision->fee_sheet_type,
                'project_id'            => $currentRevision->project_id,
                'project_name'          => $currentRevision->project_name,
                'discipline_id'         => $currentRevision->discipline_id,
                'director_in_charge_id' => $currentRevision->director_in_charge_id,
                'client_name'           => $currentRevision->client_name,
                'location'              => $currentRevision->location,
                'mtl_scope_detail'      => $currentRevision->mtl_scope_detail,
                'contact_name'          => $currentRevision->contact_name,
                'comment'               => $currentRevision->comment,

                'project_type_id'       => $currentRevision->project_type_id,
                'form_filled_by_id'     => $currentRevision->form_filled_by_id,
                'form_filled_by_date'   => $currentRevision->form_filled_by_date,
                'approved_by_ch_id'     => $currentRevision->approved_by_ch_id,
                'approved_by_ch_date'   => $currentRevision->approved_by_ch_date,
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

}
