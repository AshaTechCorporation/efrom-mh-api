<?php
namespace App\Http\Controllers;

use App\Models\DesignReview;
use App\Models\DesignReviewAnswer;
use App\Models\DesignReviewAssignment;
use App\Models\DesignReviewDocument;
use App\Models\Discipline;
use App\Models\MenuPermission;
use App\Models\ProposalContractReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignReviewController extends Controller
{
    private const MENU_KEY = 'design_reviews';

    private function unauthorizedResponse()
    {
        return response()->json([
            'code' => '401',
            'status' => false,
            'message' => 'Unauthorized',
            'data' => [],
        ], 401);
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'code' => '403',
            'status' => false,
            'message' => 'Forbidden',
            'data' => [],
        ], 403);
    }

    private function resolveLoginUserId(Request $request): ?int
    {
        if (isset($request->login_id) && is_numeric($request->login_id)) {
            return (int) $request->login_id;
        }

        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            if (isset($loginBy->id) && is_numeric($loginBy->id)) {
                return (int) $loginBy->id;
            }
            if (isset($loginBy->user_id) && is_numeric($loginBy->user_id)) {
                return (int) $loginBy->user_id;
            }
        }

        if (is_array($loginBy)) {
            if (isset($loginBy['id']) && is_numeric($loginBy['id'])) {
                return (int) $loginBy['id'];
            }
            if (isset($loginBy['user_id']) && is_numeric($loginBy['user_id'])) {
                return (int) $loginBy['user_id'];
            }
        }

        return null;
    }

    private function resolvePermissionId(Request $request, ?int $userId): ?int
    {
        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy) && isset($loginBy->permission_id) && is_numeric($loginBy->permission_id)) {
            return (int) $loginBy->permission_id;
        }
        if (is_array($loginBy) && isset($loginBy['permission_id']) && is_numeric($loginBy['permission_id'])) {
            return (int) $loginBy['permission_id'];
        }

        if ($userId !== null) {
            $permissionId = User::where('id', $userId)->value('permission_id');
            return is_numeric($permissionId) ? (int) $permissionId : null;
        }

        return null;
    }

    private function permissionContext(Request $request): array
    {
        $userId = $this->resolveLoginUserId($request);
        if ($userId === null) {
            return ['authorized' => false, 'status' => 401];
        }

        $permissionId = $this->resolvePermissionId($request, $userId);
        if ($permissionId === null) {
            return ['authorized' => false, 'status' => 401];
        }

        $row = MenuPermission::query()
            ->join('menus', 'menus.id', '=', 'menu_permissions.menu_id')
            ->where('menu_permissions.permission_id', $permissionId)
            ->whereNull('menu_permissions.deleted_at')
            ->whereNull('menus.deleted_at')
            ->where(function ($q) {
                $q->where('menus.key', self::MENU_KEY)
                    ->orWhere('menus.path', self::MENU_KEY)
                    ->orWhere('menus.path', '/' . self::MENU_KEY)
                    ->orWhere('menus.path', 'like', '%' . self::MENU_KEY . '%');
            })
            ->select('menu_permissions.*')
            ->first();

        $create = (int) ($row->create ?? ($row->save ?? 0));
        $viewOwn = (int) ($row->view_own ?? 0);
        $viewAll = (int) ($row->view_all ?? ($row->view ?? 0));
        $editOwn = (int) ($row->edit_own ?? 0);
        $editAll = (int) ($row->edit_all ?? ($row->edit ?? 0));
        $deleteOwn = (int) ($row->delete_own ?? 0);
        $deleteAll = (int) ($row->delete_all ?? ($row->delete ?? 0));

        $actorKeys = $this->resolveActorKeys($request, $userId);

        return [
            'authorized' => true,
            'status' => 200,
            'user_id' => $userId,
            'actor_key' => $actorKeys[0] ?? (string) $userId,
            'actor_keys' => $actorKeys,
            'create' => $create,
            'view_own' => $viewOwn,
            'view_all' => $viewAll,
            'edit_own' => $editOwn,
            'edit_all' => $editAll,
            'delete_own' => $deleteOwn,
            'delete_all' => $deleteAll,
        ];
    }

    private function resolveActorKeys(Request $request, ?int $userId): array
    {
        $keys = [];

        if ($userId !== null) {
            $keys[] = (string) $userId;
            $user = User::find($userId);
            if ($user) {
                if (!empty($user->code)) {
                    $keys[] = (string) $user->code;
                }
                if (!empty($user->username)) {
                    $keys[] = (string) $user->username;
                }
            }
        }

        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            foreach (['employee_code', 'id', 'user_id', 'username'] as $field) {
                if (isset($loginBy->{$field}) && $loginBy->{$field} !== null && $loginBy->{$field} !== '') {
                    $keys[] = (string) $loginBy->{$field};
                }
            }
        }
        if (is_array($loginBy)) {
            foreach (['employee_code', 'id', 'user_id', 'username'] as $field) {
                if (isset($loginBy[$field]) && $loginBy[$field] !== null && $loginBy[$field] !== '') {
                    $keys[] = (string) $loginBy[$field];
                }
            }
        }

        $keys = array_values(array_unique(array_filter($keys, static function ($v) {
            return $v !== '';
        })));

        return $keys;
    }

    private function ownerKeyFromRecord($record): ?string
    {
        if (isset($record->created_by) && $record->created_by !== null && $record->created_by !== '') {
            return (string) $record->created_by;
        }

        $legacy = $record->create_by ?? null;
        if ($legacy !== null && $legacy !== '') {
            return (string) $legacy;
        }

        return null;
    }

    private function ownerMatches(array $ctx, $record): bool
    {
        $ownerKey = $this->ownerKeyFromRecord($record);
        if ($ownerKey === null) {
            return false;
        }

        return in_array($ownerKey, $ctx['actor_keys'] ?? [], true);
    }

    private function canViewRecord(array $ctx, $record): bool
    {
        if (($ctx['view_all'] ?? 0) === 1) {
            return true;
        }
        if (($ctx['view_own'] ?? 0) !== 1) {
            return false;
        }

        return $this->ownerMatches($ctx, $record);
    }

    private function canEditRecord(array $ctx, $record): bool
    {
        if (($ctx['edit_all'] ?? 0) === 1) {
            return true;
        }
        if (($ctx['edit_own'] ?? 0) !== 1) {
            return false;
        }

        return $this->ownerMatches($ctx, $record);
    }

    private function applyViewScope($query, array $ctx): void
    {
        if (($ctx['view_all'] ?? 0) === 1) {
            return;
        }

        if (($ctx['view_own'] ?? 0) === 1) {
            $keys = $ctx['actor_keys'] ?? [];
            if (empty($keys)) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->where(function ($q) use ($keys) {
                $q->whereIn('created_by', $keys)
                    ->orWhereIn('create_by', $keys);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function modulePermissionPayload(array $ctx): array
    {
        return [
            'create' => (bool) ($ctx['create'] ?? 0),
            'view_own' => (bool) ($ctx['view_own'] ?? 0),
            'view_all' => (bool) ($ctx['view_all'] ?? 0),
            'edit_own' => (bool) ($ctx['edit_own'] ?? 0),
            'edit_all' => (bool) ($ctx['edit_all'] ?? 0),
            'delete_own' => (bool) ($ctx['delete_own'] ?? 0),
            'delete_all' => (bool) ($ctx['delete_all'] ?? 0),
        ];
    }

    private function recordPermissionPayload(array $ctx, $record): array
    {
        return [
            'can_view' => $this->canViewRecord($ctx, $record),
            'can_edit' => $this->canEditRecord($ctx, $record),
            'can_delete' => false,
        ];
    }

    /**
     * GET /pages/design_review_page
     * Get master data for create form
     */
    public function getPage()
    {
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1 && ($ctx['create'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

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
            'permissions' => $this->modulePermissionPayload($ctx),
        ]);
    }

    /**
     * POST /design_reviews
     * Store new design review
     */
    public function store(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['create'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $loginBy = $request->login_by;
        DB::beginTransaction();

        try {

            $data = $request->validate([
                'project_no'                      => 'required|string',
                'project_name'                    => 'required|string',
                'prepare_by'                      => 'required|string',
                'discipline_id'                   => 'required|integer|exists:disciplines,id',

                'document_types'                  => 'nullable|array',
                'document_types.*'                => 'string',
                'document_location'               => 'nullable|string',

                'assignments.reviewer_for_action' => 'required|string',
                'assignments.teamlead_for_action' => 'required|string',
                'assignments.director_for_action' => 'required|string',

                'answers'                         => 'required|array|size:5',
                'answers.*.question_no'           => 'required|integer|min:1|max:5',
                'answers.*.answer'                => 'required|string|in:Yes,No,N/A',

                'comments'                        => 'nullable|string',

                'first_signed_by'                 => 'nullable|string',
                'first_signed_status'             => 'nullable|string',
                'first_signed_date'               => 'nullable|date',

                'responded_by'                    => 'nullable|string',
                'responded_status'                => 'nullable|string',
                'responded_date'                  => 'nullable|date',

                'recommended_action'              => 'nullable|in:Yes,No',
                'recommended_note'                => 'nullable|string',

                'second_signed_by'                => 'nullable|string',
                'second_signed_status'            => 'nullable|string',
                'second_signed_date'              => 'nullable|date',

                'tl_mep_signed_by'                => 'nullable|string',
                'tl_mep_signed_status'            => 'nullable|string',
                'tl_mep_signed_date'              => 'nullable|date',

                'tl_signed_by'                    => 'nullable|string',
                'tl_signed_status'                => 'nullable|string',
                'tl_signed_date'                  => 'nullable|date',

                'acknowledged_by'                 => 'nullable|string',
                'acknowledged_status'             => 'nullable|string',
                'acknowledged_date'               => 'nullable|date',
            ]);

            $designReview = DesignReview::create([
                'project_name'         => $data['project_name'],
                'project_no'           => $data['project_no'],
                'prepare_by'           => $data['prepare_by'],
                'discipline_id'        => $data['discipline_id'],

                'document_location'    => $data['document_location'],
                'comments'             => $data['comments'],

                'first_signed_by'      => $data['first_signed_by'],
                'first_signed_status'  => $data['first_signed_status'] ?? 'pending',
                'first_signed_date'    => $data['first_signed_date'] ?? null,

                'responded_by'         => $data['responded_by'],
                'responded_status'     => $data['responded_status'] ?? 'pending',
                'responded_date'       => $data['responded_date'],

                'recommended_action'   => $data['recommended_action'],
                'recommended_note'     => $data['recommended_note'],

                'second_signed_by'     => $data['second_signed_by'],
                'second_signed_status' => $data['second_signed_status'] ?? 'pending',
                'second_signed_date'   => $data['second_signed_date'] ?? null,

                'tl_mep_signed_by'     => $data['tl_mep_signed_by'],
                'tl_mep_signed_status' => $data['tl_mep_signed_status'] ?? 'pending',
                'tl_mep_signed_date'   => $data['tl_mep_signed_date'] ?? null,

                'tl_signed_by'         => $data['tl_signed_by'],
                'tl_signed_status'     => $data['tl_signed_status'] ?? 'pending',
                'tl_signed_date'       => $data['tl_signed_date'] ?? null,

                'acknowledged_by'      => $data['acknowledged_by'],
                'acknowledged_status'  => $data['acknowledged_status'],
                'acknowledged_date'    => $data['acknowledged_date'],

                'create_by'            => $loginBy->id ?? 'admin',
                'created_by'           => (string) ($ctx['actor_key'] ?? ($ctx['user_id'] ?? '')),
                'update_by'            => $loginBy->id ?? 'admin',
            ]);

            if (! empty($data['document_types'])) {
                foreach ($data['document_types'] as $docType) {
                    DesignReviewDocument::create([
                        'design_review_id' => $designReview->id,
                        'document_type'    => $docType,
                    ]);
                }
            }

            DesignReviewAssignment::create([
                'design_review_id'    => $designReview->id,
                'reviewer_for_action' => $data['assignments']['reviewer_for_action'],
                'teamlead_for_action' => $data['assignments']['teamlead_for_action'],
                'director_for_action' => $data['assignments']['director_for_action'],
            ]);

            foreach ($data['answers'] as $answer) {
                DesignReviewAnswer::create([
                    'design_review_id' => $designReview->id,
                    'question_no'      => $answer['question_no'],
                    'answer'           => $answer['answer'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Design Review created successfully',
                'permissions' => $this->modulePermissionPayload($ctx),
                'data'    => $designReview->fresh(),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create a design review',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /design_reviews/{id}
     */
    public function update(Request $request, $id)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $loginBy = $request->login_by;

        DB::beginTransaction();

        try {

            $designReview = DesignReview::findOrFail($id);
            if (!$this->canEditRecord($ctx, $designReview)) {
                return $this->forbiddenResponse();
            }

            $data = $request->validate([
                'project_no'                      => 'required|string',
                'project_name'                    => 'required|string',
                'prepare_by'                      => 'required|string',
                'discipline_id'                   => 'required|integer|exists:disciplines,id',

                'document_types'                  => 'nullable|array',
                'document_types.*'                => 'string',
                'document_location'               => 'nullable|string',

                'assignments.reviewer_for_action' => 'required|string',
                'assignments.teamlead_for_action' => 'required|string',
                'assignments.director_for_action' => 'required|string',

                'answers'                         => 'required|array|size:5',
                'answers.*.question_no'           => 'required|integer|min:1|max:5',
                'answers.*.answer'                => 'required|string|in:Yes,No,N/A',

                'comments'                        => 'nullable|string',

                
                'first_signed_by'                 => 'nullable|string',
                'first_signed_status'             => 'nullable|string',
                'first_signed_date'               => 'nullable|date',

                'responded_by'                    => 'nullable|string',
                'responded_status'                => 'nullable|string',
                'responded_date'                  => 'nullable|date',

                'recommended_action'              => 'nullable|in:Yes,No',
                'recommended_note'                => 'nullable|string',

                'second_signed_by'                => 'nullable|string',
                'second_signed_status'            => 'nullable|string',
                'second_signed_date'              => 'nullable|date',

                'tl_mep_signed_by'                => 'nullable|string',
                'tl_mep_signed_status'            => 'nullable|string',
                'tl_mep_signed_date'              => 'nullable|date',

                'tl_signed_by'                    => 'nullable|string',
                'tl_signed_status'                => 'nullable|string',
                'tl_signed_date'                  => 'nullable|date',

                'acknowledged_by'                 => 'nullable|string',
                'acknowledged_status'             => 'nullable|string',
                'acknowledged_date'               => 'nullable|date',
            ]);

            $designReview->update([
                'project_name'         => $data['project_name'],
                'project_no'           => $data['project_no'],
                'prepare_by'           => $data['prepare_by'],
                'discipline_id'        => $data['discipline_id'],

                'document_location'    => $data['document_location'] ?? null,
                'comments'             => $data['comments'] ?? null,

                'first_signed_by'      => $data['first_signed_by'] ?? null,
                'first_signed_status'  => $data['first_signed_status'] ?? $designReview->first_signed_status,
                'first_signed_date'    => $data['first_signed_date'] ?? null,

                'responded_by'         => $data['responded_by'] ?? null,
                'responded_status'     => $data['responded_status'] ?? $designReview->responded_status,
                'responded_date'       => $data['responded_date'] ?? null,

                'recommended_action'   => $data['recommended_action'] ?? null,
                'recommended_note'     => $data['recommended_note'] ?? null,

                'second_signed_by'     => $data['second_signed_by'] ?? null,
                'second_signed_status' => $data['second_signed_status'] ?? $designReview->second_signed_status,
                'second_signed_date'   => $data['second_signed_date'] ?? null,

                'tl_mep_signed_by'     => $data['tl_mep_signed_by'] ?? null,
                'tl_mep_signed_status' => $data['tl_mep_signed_status'] ?? $designReview->tl_mep_signed_status,
                'tl_mep_signed_date'   => $data['tl_mep_signed_date'] ?? null,

                'tl_signed_by'         => $data['tl_signed_by'] ?? null,
                'tl_signed_status'     => $data['tl_signed_status'] ?? $designReview->tl_signed_status,
                'tl_signed_date'       => $data['tl_signed_date'] ?? null,

                'acknowledged_by'      => $data['acknowledged_by'] ?? null,
                'acknowledged_status'  => $data['acknowledged_status'] ?? $designReview->acknowledged_status,
                'acknowledged_date'    => $data['acknowledged_date'] ?? null,

                'update_by'            => $loginBy->id ?? 'admin',
            ]);

            DesignReviewDocument::where('design_review_id', $designReview->id)->delete();

            if (! empty($data['document_types'])) {
                foreach ($data['document_types'] as $docType) {
                    DesignReviewDocument::create([
                        'design_review_id' => $designReview->id,
                        'document_type'    => $docType,
                    ]);
                }
            }

            DesignReviewAssignment::updateOrCreate(
                ['design_review_id' => $designReview->id],
                [
                    'reviewer_for_action' => $data['assignments']['reviewer_for_action'],
                    'teamlead_for_action' => $data['assignments']['teamlead_for_action'],
                    'director_for_action' => $data['assignments']['director_for_action'],
                ]
            );

            DesignReviewAnswer::where('design_review_id', $designReview->id)->delete();

            foreach ($data['answers'] as $answer) {
                DesignReviewAnswer::create([
                    'design_review_id' => $designReview->id,
                    'question_no'      => $answer['question_no'],
                    'answer'           => $answer['answer'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Design Review updated successfully',
                'permissions' => $this->modulePermissionPayload($ctx),
                'data'    => $designReview->fresh(),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update design review',
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
        $ctx = $this->permissionContext(request());
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }

        $designReview = DesignReview::with([
            'discipline',
            'answers',
            'documents',
            'assignment',
        ])->find($id);

        if (! $designReview) {
            return response()->json([
                'message' => 'Design Review not found',
            ], 404);
        }
        if (!$this->canViewRecord($ctx, $designReview)) {
            return $this->forbiddenResponse();
        }
        $designReview->permissions = $this->recordPermissionPayload($ctx, $designReview);

        return response()->json([
            'message' => 'Design Review retrieved successfully',
            'permissions' => $this->modulePermissionPayload($ctx),
            'data'    => $designReview,
        ], 200);
    }

    /**
     * GET /design_reviews
     * List reviews with pagination & filters
     */
    public function getList(Request $request)
    {
        $ctx = $this->permissionContext($request);
        if (!($ctx['authorized'] ?? false)) {
            return $this->unauthorizedResponse();
        }
        if (($ctx['view_all'] ?? 0) !== 1 && ($ctx['view_own'] ?? 0) !== 1) {
            return $this->forbiddenResponse();
        }

        $draw   = intval($request->input('draw'));
        $start  = intval($request->input('start', 0));
        $length = intval($request->input('length', 10));
        $search = $request->input('search.value');

        $columns = [
            'id',
            'project_no',
            'project_name',
            'discipline_id',
            'first_signed_status',
            'created_at',
        ];

        $query = DesignReview::query()
            ->with(['discipline']);
        $this->applyViewScope($query, $ctx);

        // Total records
        $recordsTotal = $query->count();

        // Global search
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('project_no', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%")
                    ->orWhere('first_signed_status', 'like', "%{$search}%")
                    ->orWhereHas('discipline', function ($d) use ($search) {
                        $d->where('name', 'like', "%{$search}%");
                    });
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
        $rows = $data->map(function ($item) use ($ctx) {

            // You can later improve this logic to compute overall status
            $status = $item->first_signed_status ?? 'draft';

            return [
                'id'           => $item->id,
                'project_no'   => $item->project_no,
                'project_name' => $item->project_name,
                'discipline'   => $item->discipline->name ?? '-',
                'status'       => $status,
                'created_at'   => $item->created_at->format('Y-m-d'),
                'permissions'  => $this->recordPermissionPayload($ctx, $item),
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'permissions'     => $this->modulePermissionPayload($ctx),
            'data'            => $rows,
        ]);
    }

}
