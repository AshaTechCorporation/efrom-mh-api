<?php
namespace App\Http\Controllers;

use App\Models\FeeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeSheetController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fee_sheet_type'        => 'required|string|in:project,facade,lighting,transportation',
            'project_id'            => 'nullable|exists:proposal_contract_reviews,id',
            'discipline_id'         => 'nullable|exists:disciplines,id',
            'project_type_id'       => 'nullable|exists:project_types,id',
            'director_in_charge_id' => 'nullable|exists:users,id',

            'mt_project_no'         => 'nullable|string',
            'project_name'          => 'nullable|string',
            'client_name'           => 'nullable|string',
            'location'              => 'nullable|string',
            'mtl_scope_detail'      => 'nullable|string',
            'contact_name'          => 'nullable|string',
            'comment'               => 'nullable|string',

            'form_filled_by_id'     => 'nullable|string',
            'form_filled_by_date'   => 'nullable|date',
            'approved_by_ch_id'     => 'nullable|string',
            'approved_by_ch_date'   => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {

            $feeSheet = FeeSheet::create($validated);

            if ($request->team) {
                $feeSheet->teamMembers()->create([
                    'employee_code' => $request->team,
                ]);
            }

            if ($request->fee_agreements) {
                $feeSheet->feeAgreements()->createMany($request->fee_agreements);
            }

            if ($request->job_costing) {
                $feeSheet->jobCostings()->createMany($request->job_costing);
            }

            if ($request->billing_forecast) {
                $feeSheet->billingForecasts()->createMany($request->billing_forecast);
            }

            DB::commit();

            return response()->json(
                $feeSheet->load([
                    'project',
                    'discipline',
                    'projectType',
                    'directorInCharge',
                    'teamMembers',
                    'feeAgreements',
                    'jobCostings',
                    'billingForecasts',
                ]),
                201
            );

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error'   => 'Failed to create fee sheet',
                'message' => $e->getMessage(),
            ], 500);
        }
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
            'sort_by'               => 'nullable|string|in:created_at,updated_at,project_name,mt_project_no',
            'sort_direction'        => 'nullable|string|in:asc,desc',
            'per_page'              => 'nullable|integer|in:10,25,50,100',
        ]);

        $query = FeeSheet::with([
            'project',
            'discipline',
            'projectType',
            'directorInCharge',
        ]);

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
            'discipline',
            'projectType',
            'directorInCharge',
            'teamMembers',
            'feeAgreements',
            'jobCostings',
            'billingForecasts',
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

        $feeSheet = FeeSheet::find($id);

        if (! $feeSheet) {
            return response()->json([
                'message' => "Fee sheet with ID {$id} not found.",
            ], 404);
        }

        $feeSheet->delete();

        return response()->json([
            'message' => "Fee sheet with ID {$id} has been deleted.",
            'deleted_at' => $feeSheet->deleted_at,
        ]);
    }

}
