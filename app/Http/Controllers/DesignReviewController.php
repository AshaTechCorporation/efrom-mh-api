<?php
namespace App\Http\Controllers;

use App\Models\DesignReview;
use App\Models\DesignReviewSignature;
use App\Models\Discipline;
use App\Models\ProposalContractReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignReviewController extends Controller
{
    /**
     * GET /pages/design_review_page
     * Get master data for create form
     */
    public function getPage()
    {
        $disciplines = Discipline::where('is_active', 1)->select('id', 'code', 'name')->orderBy('name')->get();
        $projects    = ProposalContractReview::whereNull('deleted_at')->select('id', 'project_name', 'project_no')->orderBy('project_name')->get();
        $users       = User::where('status', 'Yes')
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'projects'    => $projects,
            'disciplines' => $disciplines,
            'users'       => $users,
        ]);
    }

    /**
     * POST /design_reviews
     * Store new design review
     */
    public function store(Request $request)
    {

        $actorId = $this->resolveActorId($request);

        $validated = $request->validate([
            'project_id'                    => 'required|exists:project_types,id',
            'discipline_id'                 => 'required|exists:disciplines,id',
            'prepared_by'                   => 'required|exists:users,id',
            'comments'                      => 'nullable|string',

            'answers'                       => 'required|array|size:5',
            'answers.*.question_no'         => 'required|integer|between:1,5|distinct',
            'answers.*.answer'              => 'required|string',

            'documents'                     => 'required|array|min:1',
            'documents.*.document_type'     => 'required|string',
            'documents.*.document_location' => 'nullable|string',

            'assignment.reviewer_id'        => 'required|exists:users,id',
            'assignment.team_lead_id'       => 'required|exists:users,id',
            'assignment.director_id'        => 'required|exists:users,id',
        ]);

        DB::beginTransaction();

        try {
            $designReview = DesignReview::create([
                'project_id'    => $validated['project_id'],
                'discipline_id' => $validated['discipline_id'],
                'prepared_by'   => $validated['prepared_by'],
                'created_by'    => $actorId,
                'comments'      => $validated['comments'] ?? null,
                'status'        => 'Draft',
            ]);
            foreach ($validated['answers'] as $answer) {
                $designReview->answers()->create([
                    'question_no' => $answer['question_no'],
                    'answer'      => $answer['answer'],
                ]);
            }

            if (! empty($validated['documents'])) {
                foreach ($validated['documents'] as $doc) {
                    $designReview->documents()->create([
                        'document_type'     => $doc['document_type'],
                        'document_location' => $doc['document_location'],
                    ]);
                }
            }

            $designReview->assignment()->create([
                'reviewer_id'  => $validated['assignment']['reviewer_id'],
                'team_lead_id' => $validated['assignment']['team_lead_id'],
                'director_id'  => $validated['assignment']['director_id'],
            ]);

            DesignReviewSignature::create([
                'design_review_id' => $designReview->id,
                'role'             => 'Creator',
                'user_id'          => $actorId,
                'action_status'    => 'Submitted',
                'action_at'        => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Design Review created successfully',
                'data'    => $designReview->load([
                    'answers',
                    'documents',
                    'assignment',
                    'signatures',
                ]),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create Design Review',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /design_reviews/{id}
     * Update draft / returned review
     */
    public function update(Request $request, $id)
    {
        $actorId = $this->resolveActorId($request);

        $validated = $request->validate([
            'project_id'                    => 'required|exists:project_types,id',
            'discipline_id'                 => 'required|exists:disciplines,id',
            'prepared_by'                   => 'required|exists:users,id',
            'comments'                      => 'nullable|string',

            'answers'                       => 'required|array|size:5',
            'answers.*.question_no'         => 'required|integer|between:1,5|distinct',
            'answers.*.answer'              => 'required|string',

            'documents'                     => 'required|array|min:1',
            'documents.*.document_type'     => 'required|string',
            'documents.*.document_location' => 'nullable|string',

            'assignment.reviewer_id'        => 'required|exists:users,id',
            'assignment.team_lead_id'       => 'required|exists:users,id',
            'assignment.director_id'        => 'required|exists:users,id',
        ]);

        $designReview = DesignReview::findOrFail($id);

        if ($designReview->status !== 'Draft') {
            return response()->json([
                'message' => 'Only Draft records can be updated',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Update main record
            $designReview->update([
                'project_id'    => $validated['project_id'],
                'discipline_id' => $validated['discipline_id'],
                'prepared_by'   => $validated['prepared_by'],
                'comments'      => $validated['comments'] ?? null,
            ]);

            // Replace answers
            $designReview->answers()->delete();
            foreach ($validated['answers'] as $answer) {
                $designReview->answers()->create([
                    'question_no' => $answer['question_no'],
                    'answer'      => $answer['answer'],
                ]);
            }

            // Replace documents
            $designReview->documents()->delete();
            if (! empty($validated['documents'])) {
                foreach ($validated['documents'] as $doc) {
                    $designReview->documents()->create([
                        'document_type'     => $doc['document_type'],
                        'document_location' => $doc['document_location'],
                    ]);
                }
            }

            // Replace assignment
            $designReview->assignment()->delete();
            $designReview->assignment()->create([
                'reviewer_id'  => $validated['assignment']['reviewer_id'],
                'team_lead_id' => $validated['assignment']['team_lead_id'],
                'director_id'  => $validated['assignment']['director_id'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Design Review updated successfully',
                'data'    => $designReview->load([
                    'answers',
                    'documents',
                    'assignment',
                    'signatures',
                ]),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update Design Review',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /design_reviews/{id}
     * Get review detail
     */
    public function getById($id)
    {
        $designReview = DesignReview::with([
            'project',
            'discipline',
            'preparedBy',
            'createdBy',
            'answers',
            'documents',
            'assignment',
            'signatures',
        ])->find($id);

        if (! $designReview) {
            return response()->json([
                'message' => 'Design Review not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Design Review retrieved successfully',
            'data'    => $designReview,
        ], 200);
    }

    /**
     * GET /design_reviews
     * List reviews with pagination & filters
     */
    public function getList(Request $request)
    {
        $draw   = intval($request->input('draw'));
        $start  = intval($request->input('start', 0));
        $length = intval($request->input('length', 10));
        $search = $request->input('search.value');

        $columns = [
            'id',
            'project_id',
            'discipline_id',
            'prepared_by',
            'status',
            'created_at',
        ];

        $query = DesignReview::query()
            ->with(['project', 'discipline', 'preparedBy']);

        // Total records
        $recordsTotal = $query->count();

        // Global search
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                    ->orWhereHas('project', fn($p) =>
                        $p->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('discipline', fn($d) =>
                        $d->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('preparedBy', fn($u) =>
                        $u->where('name', 'like', "%{$search}%"));
            });
        }

        // Filtered count
        $recordsFiltered = $query->count();

        // Ordering
        if ($request->has('order.0.column')) {
            $columnIndex = $request->input('order.0.column');
            $direction   = $request->input('order.0.dir', 'desc');

            $orderColumn = $columns[$columnIndex] ?? 'created_at';
            $query->orderBy($orderColumn, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $data = $query
            ->skip($start)
            ->take($length)
            ->get();

        // Format rows
        $rows = $data->map(function ($item) {
            return [
                'id'          => $item->id,
                'project'     => $item->project->name ?? '-',
                'discipline'  => $item->discipline->name ?? '-',
                'prepared_by' => $item->preparedBy->name ?? '-',
                'status'      => $item->status,
                'created_at'  => $item->created_at->format('Y-m-d'),
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

}
