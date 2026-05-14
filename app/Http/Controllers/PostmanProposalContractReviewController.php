<?php

namespace App\Http\Controllers;

use App\Mail\NotificationMail;
use App\Models\PostmanProposalContractReview;
use App\Models\ProposalContractReviewApproval;
use App\Services\ProposalContractReviewNumberService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PostmanProposalContractReviewController extends JsonPayloadCrudController
{
    private const STATUS_PENDING_PROPOSAL = 'pending_proposal_review';
    private const STATUS_PENDING_CONTRACT = 'pending_contract_review';
    private const STATUS_CONTRACT_APPROVED = 'contract_approved';
    private const STATUS_DECLINED = 'declined';

    protected string $modelClass = PostmanProposalContractReview::class;

    protected array $coreFieldMap = [
        'project_name' => ['project_name', 'projectName'],
        'project_no' => ['project_no', 'projectNo', 'projectNumber'],
        'proposal_number' => ['proposal_number', 'proposalNumber'],
        'primary_discipline' => ['primary_discipline', 'primaryDiscipline'],
        'mt_project_no' => ['mt_project_no', 'mtProjectNo'],
        'client_name' => ['client_name', 'clientName'],
        'project_type' => ['project_type', 'projectType', 'project_type_id'],
        'currency' => ['currency'],
        'estimated_total_fees' => ['estimated_total_fees', 'estimatedTotalFees'],
        'city' => ['city'],
        'country' => ['country'],
        'filled_in_by' => ['filled_in_by', 'preparedBy', 'filledInBy'],
        'proposal_to_be_submitted' => ['proposal_to_be_submitted'],
        'proposal_decision' => ['proposal_decision', 'proposalDecision'],
        'win_probability' => ['win_probability', 'winProbability'],
        'contract_agreed_to_proceed' => ['contract_agreed_to_proceed'],
        'contract_decision' => ['contract_decision', 'contractDecision'],
        'need_quality_plan_pqp' => ['need_quality_plan_pqp', 'needQualityPlanPqp'],
        'root_review_id' => ['root_review_id', 'rootReviewId'],
        'revision_no' => ['revision_no', 'revisionNo'],
        'revision_label' => ['revision_label', 'revisionLabel'],
        'revision_reason' => ['revision_reason', 'revisionReason'],
        'revision_summary' => ['revision_summary', 'revisionSummary'],
        'revised_from_id' => ['revised_from_id', 'revisedFromId'],
        'is_latest_revision' => ['is_latest_revision', 'isLatestRevision'],
        'status' => ['status'],
    ];

    protected array $exactFilterMap = [
        'proposal_to_be_submitted' => 'proposal_to_be_submitted',
        'proposal_decision' => 'proposal_decision',
        'contract_agreed_to_proceed' => 'contract_agreed_to_proceed',
        'contract_decision' => 'contract_decision',
        'need_quality_plan_pqp' => 'need_quality_plan_pqp',
        'status' => 'status',
        'primary_discipline' => 'primary_discipline',
        'project_type' => 'project_type',
        'currency' => 'currency',
        'root_review_id' => 'root_review_id',
        'revision_no' => 'revision_no',
        'is_latest_revision' => 'is_latest_revision',
    ];

    protected array $likeFilterMap = [
        'project_name' => 'project_name',
        'project_no' => 'project_no',
        'proposal_number' => 'proposal_number',
        'mt_project_no' => 'mt_project_no',
        'client_name' => 'client_name',
        'filled_in_by' => 'filled_in_by',
        'city' => 'city',
        'country' => 'country',
    ];

    protected array $searchableColumns = [
        'project_name',
        'project_no',
        'proposal_number',
        'mt_project_no',
        'client_name',
        'city',
        'country',
        'filled_in_by',
        'proposal_decision',
        'contract_decision',
        'status',
    ];

    protected array $orderColumns = [
        0 => 'id',
        1 => 'project_name',
        2 => 'project_no',
        3 => 'proposal_number',
        4 => 'client_name',
        5 => 'city',
        6 => 'country',
        7 => 'status',
        8 => 'created_at',
    ];

    private ProposalContractReviewNumberService $numberService;

    public function __construct(ProposalContractReviewNumberService $numberService)
    {
        $this->numberService = $numberService;
    }

    public function getList(Request $request)
    {
        try {
            $items = $this->applyFilters($this->revisionListQuery($request)->with('approvals'), $request)
                ->orderBy('id', 'desc')
                ->get()
                ->values()
                ->map(function ($item, $index) {
                    $row = $this->transformItem($item);
                    $row['No'] = $index + 1;

                    return $row;
                });

            return $this->returnSuccess('success', $items);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getPage(Request $request)
    {
        try {
            $draw = (int) ($request->draw ?? 1);
            $start = (int) ($request->start ?? 0);
            $length = (int) ($request->length ?? 10);

            $baseQuery = $this->revisionListQuery($request);
            $query = $this->applyFilters((clone $baseQuery)->with('approvals'), $request, true);
            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();

            $this->applyOrdering($query, $request);

            $items = $query->skip($start)
                ->take($length)
                ->get()
                ->values()
                ->map(function ($item, $index) use ($start) {
                    $row = $this->transformItem($item);
                    $row['No'] = $start + $index + 1;

                    return $row;
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'draw' => (int) ($request->draw ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = $this->newQuery()->with('approvals')->where('id', $id)->first();

            if (! $item) {
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            $data = $this->transformItem($item);
            $data['revision_history'] = $this->revisionHistoryFor($item);

            return $this->returnSuccess('success', $data);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function revisions($id)
    {
        try {
            $item = $this->newQuery()->where('id', $id)->first();

            if (! $item) {
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            return $this->returnSuccess('success', $this->revisionHistoryFor($item));
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createRevision(Request $request, $id)
    {
        $incomingPayload = $this->cleanPayload($request);
        $revisionReason = $this->payloadValue($incomingPayload, ['revision_reason', 'revisionReason']);
        $revisionSummary = $this->payloadValue($incomingPayload, ['revision_summary', 'revisionSummary']);

        if ($revisionReason === null) {
            return $this->errorResponse('กรุณาระบุเหตุผลในการสร้าง Revision', 422);
        }

        DB::beginTransaction();

        try {
            $source = $this->newQuery()->where('id', $id)->lockForUpdate()->first();
            if (! $source) {
                DB::rollBack();
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            $rootId = $this->revisionRootId($source);
            $latest = $this->newQuery()
                ->with('approvals')
                ->where('root_review_id', $rootId)
                ->where('is_latest_revision', true)
                ->lockForUpdate()
                ->first();

            if (! $latest) {
                $latest = $source->fresh('approvals') ?: $source;
            }

            if (! $latest->is_latest_revision || $latest->locked_at !== null) {
                DB::rollBack();
                return $this->errorResponse('เอกสารนี้มี Revision ใหม่กว่าแล้ว ไม่สามารถสร้าง Revision จากรายการนี้ได้', 422);
            }

            $storedPayload = $this->payloadArray($latest);
            $payload = $this->stripRuntimePayload(array_merge($storedPayload, $incomingPayload));
            $validation = $this->validateCreatePayload($payload, false);
            if ($validation !== null) {
                DB::rollBack();
                return $this->errorResponse($validation, 422);
            }

            $approvers = $this->resolveApprovers($payload);
            if (count($approvers) !== 3) {
                $approvers = $this->approversFromExistingStage($latest, 'proposal');
            }

            if (count($approvers) !== 3) {
                DB::rollBack();
                return $this->errorResponse('กรุณาระบุผู้อนุมัติ 3 คนสำหรับ Revision ใหม่', 422);
            }

            $actorId = $this->resolveActorId($request);
            $nextRevisionNo = ((int) $this->newQuery()
                ->where('root_review_id', $rootId)
                ->max('revision_no')) + 1;
            $now = Carbon::now();

            $latest->is_latest_revision = false;
            $latest->locked_at = $now;
            $latest->update_by = $actorId;
            $latest->save();

            $item = new PostmanProposalContractReview();
            $item->project_name = $this->payloadValue($payload, ['project_name', 'projectName']);
            $item->project_no = $latest->project_no;
            $item->proposal_number = $latest->proposal_number;
            $item->primary_discipline = $latest->primary_discipline;
            $item->mt_project_no = $latest->mt_project_no;
            $item->client_name = $this->payloadValue($payload, ['client_name', 'clientName']);
            $item->project_type = $this->payloadValue($payload, ['project_type', 'projectType', 'project_type_id']);
            $item->currency = strtoupper((string) $this->payloadValue($payload, ['currency']));
            $item->estimated_total_fees = $this->numericValue(
                $this->payloadValue($payload, ['estimated_total_fees', 'estimatedTotalFees'])
            );
            $item->city = $this->payloadValue($payload, ['city']);
            $item->country = $this->payloadValue($payload, ['country']);
            $item->filled_in_by = $this->payloadValue($payload, ['filled_in_by', 'preparedBy', 'filledInBy']);
            $item->proposal_to_be_submitted = null;
            $item->proposal_decision = null;
            $item->win_probability = null;
            $item->contract_agreed_to_proceed = null;
            $item->contract_decision = null;
            $item->need_quality_plan_pqp = null;
            $item->root_review_id = $rootId;
            $item->revision_no = $nextRevisionNo;
            $item->revision_label = 'Rev.' . $nextRevisionNo;
            $item->revision_reason = $revisionReason;
            $item->revision_summary = $revisionSummary;
            $item->revised_from_id = $latest->id;
            $item->is_latest_revision = true;
            $item->locked_at = null;
            $item->status = self::STATUS_PENDING_PROPOSAL;
            $item->submitted_at = $now;
            $item->proposal_reviewed_at = null;
            $item->contract_reviewed_at = null;
            $item->completed_at = null;
            $item->create_by = $actorId;
            $item->update_by = $actorId;

            $this->putWorkflowValuesIntoPayload($payload, $item);
            $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $item->save();

            $this->createApprovalRows($item, $approvers, $actorId);

            DB::commit();

            $item->load('approvals');
            $this->notifyApprovers($item, 'proposal');

            $data = $this->transformItem($item);
            $data['revision_history'] = $this->revisionHistoryFor($item);

            return $this->returnSuccess('สร้าง Revision สำเร็จ', $data);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        $payload = $this->cleanPayload($request);
        $validation = $this->validateCreatePayload($payload);
        if ($validation !== null) {
            return $this->errorResponse($validation, 422);
        }

        $approvers = $this->resolveApprovers($payload);
        if (count($approvers) !== 3) {
            return $this->errorResponse('กรุณาระบุผู้อนุมัติ 3 คน โดยคนแรกเป็น MD/DI และอีก 2 คนเป็น DI', 422);
        }

        DB::beginTransaction();

        try {
            $actorId = $this->resolveActorId($request);
            $discipline = $this->numberService->normalizeDiscipline(
                $this->payloadValue($payload, ['primary_discipline', 'primaryDiscipline']),
                $payload
            );
            $proposalNumber = $this->numberService->nextProposalNumber($discipline);

            $item = new PostmanProposalContractReview();
            $item->project_name = $this->payloadValue($payload, ['project_name', 'projectName']);
            $item->project_no = null;
            $item->proposal_number = $proposalNumber;
            $item->primary_discipline = $discipline;
            $item->mt_project_no = null;
            $item->client_name = $this->payloadValue($payload, ['client_name', 'clientName']);
            $item->project_type = $this->payloadValue($payload, ['project_type', 'projectType', 'project_type_id']);
            $item->currency = strtoupper((string) $this->payloadValue($payload, ['currency']));
            $item->estimated_total_fees = $this->numericValue($this->payloadValue($payload, ['estimated_total_fees', 'estimatedTotalFees']));
            $item->city = $this->payloadValue($payload, ['city']);
            $item->country = $this->payloadValue($payload, ['country']);
            $item->filled_in_by = $this->payloadValue($payload, ['filled_in_by', 'preparedBy', 'filledInBy']);
            $item->proposal_to_be_submitted = null;
            $item->proposal_decision = null;
            $item->win_probability = null;
            $item->contract_agreed_to_proceed = null;
            $item->contract_decision = null;
            $item->need_quality_plan_pqp = null;
            $item->root_review_id = null;
            $item->revision_no = 0;
            $item->revision_label = 'Rev.0';
            $item->revision_reason = null;
            $item->revision_summary = null;
            $item->revised_from_id = null;
            $item->is_latest_revision = true;
            $item->locked_at = null;
            $item->status = self::STATUS_PENDING_PROPOSAL;
            $item->submitted_at = Carbon::now();
            $item->create_by = $actorId;
            $item->update_by = $actorId;

            $this->putWorkflowValuesIntoPayload($payload, $item);
            $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $item->save();

            $item->root_review_id = $item->id;
            $this->putWorkflowValuesIntoPayload($payload, $item);
            $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $item->save();

            $this->createApprovalRows($item, $approvers, $actorId);

            DB::commit();

            $item->load('approvals');
            $this->notifyApprovers($item, 'proposal');

            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $this->transformItem($item));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $payload = $this->cleanPayload($request);
        $validation = $this->validateCreatePayload($payload, false);
        if ($validation !== null) {
            return $this->errorResponse($validation, 422);
        }

        DB::beginTransaction();

        try {
            $item = $this->newQuery()->with('approvals')->where('id', $id)->first();
            if (! $item) {
                DB::rollBack();
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            if (! $item->is_latest_revision || $item->locked_at !== null) {
                DB::rollBack();
                return $this->errorResponse('Revision เก่าไม่สามารถแก้ไขได้ กรุณาแก้ไข Revision ล่าสุด', 422);
            }

            if (! in_array($item->status, [self::STATUS_PENDING_PROPOSAL, 'submitted', 'draft'], true)) {
                DB::rollBack();
                return $this->errorResponse('เอกสารเข้าสู่ขั้นตอนอนุมัติแล้ว ไม่สามารถแก้ไขข้อมูลหลักได้', 422);
            }

            $actorId = $this->resolveActorId($request);
            $item->project_name = $this->payloadValue($payload, ['project_name', 'projectName']) ?? $item->project_name;
            $item->client_name = $this->payloadValue($payload, ['client_name', 'clientName']) ?? $item->client_name;
            $item->project_type = $this->payloadValue($payload, ['project_type', 'projectType', 'project_type_id']) ?? $item->project_type;
            $item->currency = strtoupper((string) ($this->payloadValue($payload, ['currency']) ?? $item->currency));
            $item->estimated_total_fees = $this->numericValue(
                $this->payloadValue($payload, ['estimated_total_fees', 'estimatedTotalFees']),
                $item->estimated_total_fees
            );
            $item->city = $this->payloadValue($payload, ['city']) ?? $item->city;
            $item->country = $this->payloadValue($payload, ['country']) ?? $item->country;
            $item->filled_in_by = $this->payloadValue($payload, ['filled_in_by', 'preparedBy', 'filledInBy']) ?? $item->filled_in_by;
            $item->update_by = $actorId;

            $storedPayload = json_decode($item->payload ?? '[]', true);
            if (! is_array($storedPayload)) {
                $storedPayload = [];
            }
            $mergedPayload = array_merge($storedPayload, $payload);
            $this->putWorkflowValuesIntoPayload($mergedPayload, $item);
            $item->payload = json_encode($mergedPayload, JSON_UNESCAPED_UNICODE);
            $item->save();

            $newApprovers = $this->resolveApprovers($payload);
            if (count($newApprovers) === 3) {
                $hasApprovalAction = $item->approvals->contains(function ($approval) {
                    return $approval->decision !== 'pending';
                });

                if ($hasApprovalAction) {
                    DB::rollBack();
                    return $this->errorResponse('มีผู้อนุมัติดำเนินการแล้ว ไม่สามารถเปลี่ยนชุดผู้อนุมัติได้', 422);
                }

                ProposalContractReviewApproval::where('proposal_contract_review_id', $item->id)->forceDelete();
                $this->createApprovalRows($item, $newApprovers, $actorId);
            }

            DB::commit();

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $this->transformItem($item->fresh('approvals')));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function nextNumber(Request $request)
    {
        try {
            $discipline = $this->numberService->normalizeDiscipline(
                $request->input('primary_discipline') ?? $request->input('discipline'),
                $request->all()
            );
            $series = $this->numberService->seriesFor($discipline);

            return $this->returnSuccess('success', [
                'primary_discipline' => $discipline,
                'proposal_prefix' => $series['proposal'],
                'mt_prefix' => $series['contract'],
                'proposal_number' => $this->numberService->nextProposalNumber($discipline),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function actionItems(Request $request)
    {
        $approverCode = $this->resolveActorCode($request);
        if ($approverCode === '') {
            return $this->errorResponse('กรุณาระบุ user_code หรือ approver_code', 422);
        }

        try {
            $approvals = ProposalContractReviewApproval::with('review.approvals')
                ->where('approver_code', $approverCode)
                ->where('decision', 'pending')
                ->whereHas('review', function ($reviewQuery) {
                    $reviewQuery->where('is_latest_revision', true);
                })
                ->where(function ($query) {
                    $query->where(function ($subQuery) {
                        $subQuery->where('stage', 'proposal')
                            ->whereHas('review', function ($reviewQuery) {
                                $reviewQuery->where('status', self::STATUS_PENDING_PROPOSAL)
                                    ->where('is_latest_revision', true);
                            });
                    })->orWhere(function ($subQuery) {
                        $subQuery->where('stage', 'contract')
                            ->whereHas('review', function ($reviewQuery) {
                                $reviewQuery->where('status', self::STATUS_PENDING_CONTRACT)
                                    ->where('is_latest_revision', true);
                            });
                    });
                })
                ->orderBy('id', 'desc')
                ->get();

            $items = $approvals->map(function ($approval) {
                $row = $this->transformItem($approval->review);
                $row['action_stage'] = $approval->stage;
                $row['action_role'] = $approval->role;
                $row['approval_id'] = $approval->id;

                return $row;
            })->values();

            return $this->returnSuccess('success', $items);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function proposalReview(Request $request, $id)
    {
        $decision = $this->resolveProposalDecision($request);
        if ($decision === null) {
            return $this->errorResponse('กรุณาเลือก Proposal to be submitted หรือ Decline', 422);
        }

        $winProbability = $this->numericValue($request->input('win_probability') ?? $request->input('winProbability'));

        DB::beginTransaction();

        try {
            $item = $this->newQuery()->with('approvals')->where('id', $id)->lockForUpdate()->first();
            if (! $item) {
                DB::rollBack();
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            if (! $item->is_latest_revision || $item->locked_at !== null) {
                DB::rollBack();
                return $this->errorResponse('Revision เก่าไม่สามารถอนุมัติได้ กรุณาเปิด Revision ล่าสุด', 422);
            }

            if ($item->status !== self::STATUS_PENDING_PROPOSAL) {
                DB::rollBack();
                return $this->errorResponse('เอกสารไม่ได้อยู่ในขั้นตอน Proposal Review', 422);
            }

            $approverCode = $this->resolveActorCode($request);
            $approval = $this->pendingApproval($item, 'proposal', $approverCode);
            if (! $approval) {
                DB::rollBack();
                return $this->errorResponse('ผู้ใช้นี้ไม่มีสิทธิ์อนุมัติหรือได้อนุมัติไปแล้ว', 403);
            }

            if ($item->proposal_decision !== null && $item->proposal_decision !== $decision) {
                DB::rollBack();
                return $this->errorResponse('ผลการพิจารณา Proposal ถูกเลือกไว้แล้ว กรุณาใช้ผลเดิม', 422);
            }

            if ($decision === 'submitted' && $item->win_probability === null && $winProbability === null) {
                DB::rollBack();
                return $this->errorResponse('กรุณาระบุ % Win Probability', 422);
            }

            $item->proposal_decision = $decision;
            $item->proposal_to_be_submitted = $decision === 'submitted' ? 'Yes' : 'No';
            if ($decision === 'submitted' && $item->win_probability === null) {
                $item->win_probability = $winProbability;
            }
            $item->update_by = $this->resolveActorId($request);

            $approval->decision = $decision === 'declined' ? 'declined' : 'approved';
            $approval->win_probability = $item->win_probability;
            $approval->comment = $request->input('comment');
            $approval->acted_at = Carbon::now();
            $approval->update_by = $this->resolveActorId($request);
            $approval->save();

            if ($decision === 'declined') {
                $item->status = self::STATUS_DECLINED;
                $item->proposal_reviewed_at = Carbon::now();
                $item->completed_at = Carbon::now();
            } elseif ($this->allStageApproved($item, 'proposal')) {
                $item->status = self::STATUS_PENDING_CONTRACT;
                $item->proposal_reviewed_at = Carbon::now();
            }

            $payload = $this->payloadArray($item);
            $this->putWorkflowValuesIntoPayload($payload, $item);
            $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $item->save();

            DB::commit();

            $fresh = $item->fresh('approvals');
            if ($fresh && $fresh->status === self::STATUS_PENDING_CONTRACT) {
                $this->notifyApprovers($fresh, 'contract');
            }

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $this->transformItem($fresh));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function contractReview(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $item = $this->newQuery()->with('approvals')->where('id', $id)->lockForUpdate()->first();
            if (! $item) {
                DB::rollBack();
                return $this->errorResponse('ไม่พบข้อมูล', 404);
            }

            if (! $item->is_latest_revision || $item->locked_at !== null) {
                DB::rollBack();
                return $this->errorResponse('Revision เก่าไม่สามารถอนุมัติได้ กรุณาเปิด Revision ล่าสุด', 422);
            }

            if ($item->status !== self::STATUS_PENDING_CONTRACT) {
                DB::rollBack();
                return $this->errorResponse('เอกสารไม่ได้อยู่ในขั้นตอน Contract Review', 422);
            }

            $approverCode = $this->resolveActorCode($request);
            $approval = $this->pendingApproval($item, 'contract', $approverCode);
            if (! $approval) {
                DB::rollBack();
                return $this->errorResponse('ผู้ใช้นี้ไม่มีสิทธิ์อนุมัติหรือได้อนุมัติไปแล้ว', 403);
            }

            $incomingDecision = $this->resolveContractDecision($request);
            $isDecisionMaker = $approval->role === 'MD_DI';

            if (! $isDecisionMaker && $incomingDecision !== null) {
                DB::rollBack();
                return $this->errorResponse('เฉพาะ MD/DI เท่านั้นที่เลือกผล Contract Review ได้', 422);
            }

            if ($isDecisionMaker && $item->contract_decision === null && $incomingDecision === null) {
                DB::rollBack();
                return $this->errorResponse('กรุณาเลือก Contract Agreed to Proceed หรือ Decline', 422);
            }

            if (! $isDecisionMaker && $item->contract_decision === null) {
                DB::rollBack();
                return $this->errorResponse('รอ MD/DI เลือกผล Contract Review ก่อน', 422);
            }

            if ($incomingDecision !== null && $item->contract_decision !== null && $item->contract_decision !== $incomingDecision) {
                DB::rollBack();
                return $this->errorResponse('ผล Contract Review ถูกเลือกไว้แล้ว กรุณาใช้ผลเดิม', 422);
            }

            if ($isDecisionMaker && $incomingDecision === 'proceed') {
                $needPqp = $this->resolveYesNo($request->input('need_quality_plan_pqp') ?? $request->input('needQualityPlanPqp'));
                if ($needPqp === null) {
                    DB::rollBack();
                    return $this->errorResponse('กรุณาเลือก Need to proceed a Project Quality Plan', 422);
                }

                $item->contract_decision = 'proceed';
                $item->contract_agreed_to_proceed = 'Yes';
                $item->need_quality_plan_pqp = $needPqp;

                if ($item->mt_project_no === null || $item->mt_project_no === '') {
                    $item->mt_project_no = $this->numberService->nextContractNumber($item->primary_discipline ?? 'general');
                }
                $item->project_no = $item->mt_project_no;
            } elseif ($isDecisionMaker && $incomingDecision === 'declined') {
                $item->contract_decision = 'declined';
                $item->contract_agreed_to_proceed = 'No';
                $item->need_quality_plan_pqp = null;
            }

            $approval->decision = $item->contract_decision === 'declined' ? 'declined' : 'approved';
            $approval->comment = $request->input('comment');
            $approval->acted_at = Carbon::now();
            $approval->update_by = $this->resolveActorId($request);
            $approval->save();

            if ($item->contract_decision === 'declined') {
                $item->status = self::STATUS_DECLINED;
                $item->contract_reviewed_at = Carbon::now();
                $item->completed_at = Carbon::now();
            } elseif ($this->allStageApproved($item, 'contract')) {
                $item->status = self::STATUS_CONTRACT_APPROVED;
                $item->contract_reviewed_at = Carbon::now();
                $item->completed_at = Carbon::now();
            }

            $item->update_by = $this->resolveActorId($request);
            $payload = $this->payloadArray($item);
            $this->putWorkflowValuesIntoPayload($payload, $item);
            $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $item->save();

            DB::commit();

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $this->transformItem($item->fresh('approvals')));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function revisionListQuery(Request $request)
    {
        $query = $this->newQuery();

        if (! $this->requestIncludesRevisions($request)) {
            $query->where('is_latest_revision', true);
        }

        return $query;
    }

    private function requestIncludesRevisions(Request $request): bool
    {
        foreach (['include_revisions', 'includeRevisions', 'all_revisions', 'allRevisions'] as $key) {
            if ($request->has($key)) {
                return $this->resolveBoolean($request->input($key)) === true;
            }
        }

        return false;
    }

    private function revisionRootId(PostmanProposalContractReview $item): int
    {
        return (int) ($item->root_review_id ?: $item->id);
    }

    private function revisionHistoryFor(PostmanProposalContractReview $item): array
    {
        $rootId = $this->revisionRootId($item);

        return $this->newQuery()
            ->where('root_review_id', $rootId)
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get()
            ->map(function (PostmanProposalContractReview $revision) {
                return [
                    'id' => $revision->id,
                    'root_review_id' => $revision->root_review_id,
                    'revision_no' => $revision->revision_no,
                    'revision_label' => $revision->revision_label ?: 'Rev.' . (int) ($revision->revision_no ?? 0),
                    'revision_reason' => $revision->revision_reason,
                    'revision_summary' => $revision->revision_summary,
                    'revised_from_id' => $revision->revised_from_id,
                    'is_latest_revision' => (bool) $revision->is_latest_revision,
                    'locked_at' => $revision->locked_at,
                    'status' => $revision->status,
                    'proposal_number' => $revision->proposal_number,
                    'project_no' => $revision->project_no,
                    'mt_project_no' => $revision->mt_project_no,
                    'created_at' => $revision->created_at,
                    'updated_at' => $revision->updated_at,
                    'submitted_at' => $revision->submitted_at,
                ];
            })
            ->values()
            ->all();
    }

    private function stripRuntimePayload(array $payload): array
    {
        foreach ([
            'id',
            'No',
            'approvals',
            'approval_id',
            'action_stage',
            'action_role',
            'current_stage',
            'revision_display',
            'revision_history',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
            'submitted_at',
            'proposal_reviewed_at',
            'contract_reviewed_at',
            'completed_at',
            'locked_at',
        ] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    private function approversFromExistingStage(PostmanProposalContractReview $item, string $stage): array
    {
        if (! $item->relationLoaded('approvals')) {
            $item->load('approvals');
        }

        return $item->approvals
            ->where('stage', $stage)
            ->sortBy('sequence')
            ->values()
            ->map(function ($approval) {
                return [
                    'code' => $approval->approver_code,
                    'name' => $approval->approver_name,
                    'email' => $approval->approver_email,
                    'role' => $approval->role,
                    'sequence' => $approval->sequence,
                ];
            })
            ->filter(function ($approver) {
                return trim((string) ($approver['code'] ?? '')) !== '';
            })
            ->values()
            ->all();
    }

    protected function transformItem(Model $item): array
    {
        $payload = $this->payloadArray($item);

        $meta = [
            'id' => $item->id,
            'create_by' => $item->create_by,
            'update_by' => $item->update_by,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'submitted_at' => $item->submitted_at,
            'proposal_reviewed_at' => $item->proposal_reviewed_at,
            'contract_reviewed_at' => $item->contract_reviewed_at,
            'completed_at' => $item->completed_at,
        ];

        foreach (array_keys($this->coreFieldMap) as $column) {
            $meta[$column] = $item->{$column};
        }

        if (! $item->relationLoaded('approvals')) {
            $item->load('approvals');
        }

        $meta['approvals'] = $item->approvals->values()->map(function ($approval) {
            return [
                'id' => $approval->id,
                'stage' => $approval->stage,
                'approver_code' => $approval->approver_code,
                'approver_name' => $approval->approver_name,
                'approver_email' => $approval->approver_email,
                'role' => $approval->role,
                'sequence' => $approval->sequence,
                'decision' => $approval->decision,
                'win_probability' => $approval->win_probability,
                'comment' => $approval->comment,
                'acted_at' => $approval->acted_at,
            ];
        })->all();

        $meta['current_stage'] = $this->currentStage($item->status);
        $meta['revision_display'] = $item->revision_label ?: 'Rev.' . (int) ($item->revision_no ?? 0);

        return array_merge($payload, $meta);
    }

    private function cleanPayload(Request $request): array
    {
        $payload = $request->except(['login_by', 'login_id']);
        unset($payload['_method']);

        return $payload;
    }

    private function validateCreatePayload(array $payload, bool $requireApprovers = true): ?string
    {
        foreach ([
            'project_name' => ['project_name', 'projectName'],
            'city' => ['city'],
            'country' => ['country'],
            'client_name' => ['client_name', 'clientName'],
            'project_type' => ['project_type', 'projectType', 'project_type_id'],
            'currency' => ['currency'],
            'filled_in_by' => ['filled_in_by', 'preparedBy', 'filledInBy'],
        ] as $label => $keys) {
            if ($this->payloadValue($payload, $keys) === null) {
                return 'กรุณาระบุ ' . $label;
            }
        }

        $currency = strtoupper((string) $this->payloadValue($payload, ['currency']));
        if (! in_array($currency, ['THB', 'USD'], true)) {
            return 'currency ต้องเป็น THB หรือ USD';
        }

        if ($requireApprovers && count($this->resolveApprovers($payload)) !== 3) {
            return 'กรุณาระบุผู้อนุมัติ 3 คน';
        }

        return null;
    }

    private function resolveApprovers(array $payload): array
    {
        $raw = [];

        if (isset($payload['approvers']) && is_array($payload['approvers'])) {
            $raw = $payload['approvers'];
        } else {
            foreach ([
                ['proposal_reviewer1', 'proposalReviewer1', 'contract_reviewer1', 'contractReviewer1'],
                ['proposal_reviewer2', 'proposalReviewer2', 'contract_reviewer2', 'contractReviewer2'],
                ['proposal_reviewer3', 'proposalReviewer3', 'contract_reviewer3', 'contractReviewer3'],
            ] as $index => $keys) {
                $code = $this->payloadValue($payload, $keys);
                if ($code !== null) {
                    $raw[] = [
                        'code' => $code,
                        'role' => $index === 0 ? 'MD_DI' : 'DI',
                    ];
                }
            }
        }

        $approvers = [];
        $seen = [];

        foreach (array_values($raw) as $index => $entry) {
            $code = is_array($entry)
                ? ($entry['code'] ?? $entry['employee_code'] ?? $entry['approver_code'] ?? $entry['value'] ?? null)
                : $entry;

            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            $employee = $this->employeeByCodeOrId($code);
            if ($employee) {
                $code = (string) $employee->code;
            }

            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $role = is_array($entry) && ! empty($entry['role'])
                ? $this->normalizeRole((string) $entry['role'])
                : ($index === 0 ? 'MD_DI' : 'DI');

            if ($index === 0) {
                $role = 'MD_DI';
            }

            $approvers[] = [
                'code' => $code,
                'name' => $employee ? trim(($employee->firstname ?? '') . ' ' . ($employee->lastname ?? '')) : null,
                'email' => $employee->email ?? null,
                'role' => $role,
                'sequence' => $index + 1,
            ];
        }

        return count($approvers) === 3 ? $approvers : [];
    }

    private function createApprovalRows(PostmanProposalContractReview $item, array $approvers, string $actorId): void
    {
        foreach (['proposal', 'contract'] as $stage) {
            foreach ($approvers as $index => $approver) {
                ProposalContractReviewApproval::create([
                    'proposal_contract_review_id' => $item->id,
                    'stage' => $stage,
                    'approver_code' => $approver['code'],
                    'approver_name' => $approver['name'],
                    'approver_email' => $approver['email'],
                    'role' => $index === 0 ? 'MD_DI' : 'DI',
                    'sequence' => $index + 1,
                    'decision' => 'pending',
                    'create_by' => $actorId,
                    'update_by' => $actorId,
                ]);
            }
        }
    }

    private function pendingApproval(PostmanProposalContractReview $item, string $stage, string $approverCode): ?ProposalContractReviewApproval
    {
        if ($approverCode === '') {
            return null;
        }

        return ProposalContractReviewApproval::query()
            ->where('proposal_contract_review_id', $item->id)
            ->where('stage', $stage)
            ->where('approver_code', $approverCode)
            ->where('decision', 'pending')
            ->first();
    }

    private function allStageApproved(PostmanProposalContractReview $item, string $stage): bool
    {
        return ProposalContractReviewApproval::query()
            ->where('proposal_contract_review_id', $item->id)
            ->where('stage', $stage)
            ->where('decision', '!=', 'approved')
            ->count() === 0;
    }

    private function payloadValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value === null) {
                return null;
            }

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_scalar($value)) {
                $string = trim((string) $value);

                return $string === '' ? null : $string;
            }
        }

        return null;
    }

    private function numericValue($value, $default = null)
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : $default;
    }

    private function payloadArray(PostmanProposalContractReview $item): array
    {
        $payload = json_decode($item->payload ?? '[]', true);

        return is_array($payload) ? $payload : [];
    }

    private function putWorkflowValuesIntoPayload(array &$payload, PostmanProposalContractReview $item): void
    {
        $payload['proposal_number'] = $item->proposal_number;
        $payload['proposalNumber'] = $item->proposal_number;
        $payload['project_no'] = $item->project_no;
        $payload['projectNo'] = $item->project_no;
        $payload['mt_project_no'] = $item->mt_project_no;
        $payload['mtProjectNo'] = $item->mt_project_no;
        $payload['primary_discipline'] = $item->primary_discipline;
        $payload['primaryDiscipline'] = $item->primary_discipline;
        $payload['proposal_decision'] = $item->proposal_decision;
        $payload['proposalDecision'] = $item->proposal_decision;
        $payload['proposal_to_be_submitted'] = $item->proposal_to_be_submitted;
        $payload['win_probability'] = $item->win_probability;
        $payload['winProbability'] = $item->win_probability;
        $payload['contract_decision'] = $item->contract_decision;
        $payload['contractDecision'] = $item->contract_decision;
        $payload['contract_agreed_to_proceed'] = $item->contract_agreed_to_proceed;
        $payload['contractAgreedToProceed'] = $item->contract_agreed_to_proceed;
        $payload['need_quality_plan_pqp'] = $item->need_quality_plan_pqp;
        $payload['needQualityPlanPqp'] = $item->need_quality_plan_pqp;
        $payload['root_review_id'] = $item->root_review_id;
        $payload['rootReviewId'] = $item->root_review_id;
        $payload['revision_no'] = $item->revision_no;
        $payload['revisionNo'] = $item->revision_no;
        $payload['revision_label'] = $item->revision_label;
        $payload['revisionLabel'] = $item->revision_label;
        $payload['revision_reason'] = $item->revision_reason;
        $payload['revisionReason'] = $item->revision_reason;
        $payload['revision_summary'] = $item->revision_summary;
        $payload['revisionSummary'] = $item->revision_summary;
        $payload['revised_from_id'] = $item->revised_from_id;
        $payload['revisedFromId'] = $item->revised_from_id;
        $payload['is_latest_revision'] = $item->is_latest_revision;
        $payload['isLatestRevision'] = $item->is_latest_revision;
        $payload['status'] = $item->status;
    }

    private function resolveProposalDecision(Request $request): ?string
    {
        $raw = strtolower(trim((string) ($request->input('proposal_decision') ?? $request->input('proposalDecision'))));
        if (in_array($raw, ['submitted', 'submit', 'proposal_to_be_submitted', 'proposal to be submitted'], true)) {
            return 'submitted';
        }
        if (in_array($raw, ['declined', 'decline', 'no'], true)) {
            return 'declined';
        }

        $submitted = $this->resolveBoolean($request->input('proposal_to_be_submitted'));
        $declined = $this->resolveBoolean($request->input('proposal_decline') ?? $request->input('proposalDecline'));

        if ($submitted === true && $declined === true) {
            return null;
        }

        if ($submitted === true) {
            return 'submitted';
        }

        if ($declined === true || $submitted === false) {
            return 'declined';
        }

        return null;
    }

    private function resolveContractDecision(Request $request): ?string
    {
        $raw = strtolower(trim((string) ($request->input('contract_decision') ?? $request->input('contractDecision'))));
        if (in_array($raw, ['proceed', 'agreed', 'contract_agreed_to_proceed', 'contract agreed to proceed'], true)) {
            return 'proceed';
        }
        if (in_array($raw, ['declined', 'decline', 'no'], true)) {
            return 'declined';
        }

        $proceed = $this->resolveBoolean($request->input('contract_agreed_to_proceed'));
        $declined = $this->resolveBoolean($request->input('contract_decline') ?? $request->input('contractDecline'));

        if ($proceed === true && $declined === true) {
            return null;
        }

        if ($proceed === true) {
            return 'proceed';
        }

        if ($declined === true || $proceed === false) {
            return 'declined';
        }

        return null;
    }

    private function resolveYesNo($value): ?string
    {
        $bool = $this->resolveBoolean($value);

        if ($bool === null) {
            return null;
        }

        return $bool ? 'Yes' : 'No';
    }

    private function resolveBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    private function resolveActorCode(Request $request): string
    {
        foreach (['approver_code', 'approverCode', 'user_code', 'userCode'] as $key) {
            $value = $request->input($key);
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        $loginBy = $request->input('login_by');
        if (is_array($loginBy)) {
            foreach (['code', 'employee_code', 'employeeCode'] as $key) {
                if (! empty($loginBy[$key])) {
                    return trim((string) $loginBy[$key]);
                }
            }
        }

        if (is_object($loginBy)) {
            foreach (['code', 'employee_code', 'employeeCode'] as $key) {
                if (! empty($loginBy->{$key})) {
                    return trim((string) $loginBy->{$key});
                }
            }
        }

        return '';
    }

    private function employeeByCodeOrId(string $code)
    {
        return DB::table('employees')
            ->where('code', $code)
            ->orWhere('id', $code)
            ->first();
    }

    private function normalizeRole(string $role): string
    {
        $normalized = strtoupper(str_replace(['/', '-', ' '], '_', trim($role)));

        return in_array($normalized, ['MD_DI', 'MD', 'DI_MD'], true) ? 'MD_DI' : 'DI';
    }

    private function currentStage(?string $status): ?string
    {
        if ($status === self::STATUS_PENDING_PROPOSAL) {
            return 'proposal';
        }

        if ($status === self::STATUS_PENDING_CONTRACT) {
            return 'contract';
        }

        return null;
    }

    private function notifyApprovers(PostmanProposalContractReview $item, string $stage): void
    {
        try {
            if (! $item->relationLoaded('approvals')) {
                $item->load('approvals');
            }

            $approvals = $item->approvals
                ->where('stage', $stage)
                ->filter(function ($approval) {
                    return ! empty($approval->approver_email) || ! empty($approval->approver_code);
                })
                ->values();

            $testRecipients = $this->notificationTestRecipients();
            $documentName = $this->notificationDocumentName($item, $stage);
            $requesterName = $this->notificationRequesterName($item);
            $requestDate = $this->notificationDate($item->submitted_at ?? $item->created_at);
            $link = $this->notificationActionLink($item);

            foreach ($approvals as $approval) {
                $targets = $testRecipients->isNotEmpty()
                    ? $testRecipients
                    : collect([$approval->approver_email])->filter()->values();

                foreach ($targets as $email) {
                    Mail::to($email)->send(new NotificationMail('action_request', "[Action Required] {$documentName}", [
                        'approver_name' => $approval->approver_name ?: ($approval->approver_code ?: 'Approver'),
                        'document_name' => $documentName,
                        'requested_by' => $requesterName,
                        'request_date' => $requestDate,
                        'link' => $link,
                    ]));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Proposal Contract Review notification failed: ' . $e->getMessage());
        }
    }

    private function notificationTestRecipients()
    {
        $testRecipient = trim((string) env('PCR_NOTIFICATION_TEST_TO', ''));
        if ($testRecipient === '') {
            return collect();
        }

        return collect(preg_split('/[,;]+/', $testRecipient))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values();
    }

    private function notificationDocumentName(PostmanProposalContractReview $item, string $stage): string
    {
        $parts = ["Proposal Contract Review #{$item->id}"];

        if (! empty($item->proposal_number)) {
            $parts[] = "({$item->proposal_number})";
        }

        if (! empty($item->revision_label)) {
            $parts[] = "[{$item->revision_label}]";
        }

        $parts[] = '-';
        $parts[] = $stage === 'contract' ? 'Contract Review' : 'Proposal Review';

        return implode(' ', $parts);
    }

    private function notificationRequesterName(PostmanProposalContractReview $item): string
    {
        $filledInBy = trim((string) ($item->filled_in_by ?? ''));
        if ($filledInBy === '') {
            return '-';
        }

        $employee = $this->employeeByCodeOrId($filledInBy);
        if (! $employee) {
            return $filledInBy;
        }

        $fullName = trim(($employee->firstname ?? '') . ' ' . ($employee->lastname ?? ''));

        return $fullName !== '' ? "{$filledInBy}, {$fullName}" : $filledInBy;
    }

    private function notificationDate($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d-M-Y');
        }

        if (! empty($value)) {
            try {
                return Carbon::parse($value)->format('d-M-Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        return '-';
    }

    private function notificationActionLink(PostmanProposalContractReview $item): string
    {
        $baseUrl = rtrim((string) env('PCR_NOTIFICATION_APP_URL', env('APP_FRONTEND_URL', 'https://edms.meinhardt.net')), '/');

        return $baseUrl . '/proposal-contract-review/review/' . urlencode((string) $item->id);
    }

    private function errorResponse(string $message, int $code)
    {
        return response()->json([
            'code' => (string) $code,
            'status' => false,
            'message' => $message,
            'data' => [],
        ], $code);
    }
}
