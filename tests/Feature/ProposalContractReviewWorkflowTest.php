<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProposalContractReviewWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.default' => 'array',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTables();
        $this->seedEmployees();
        $this->seedProjectTypes();

        Mail::fake();
    }

    public function test_create_generates_proposal_number_and_same_approvers_for_both_stages(): void
    {
        $response = $this->postJson('/api/proposal_contract_reviews', $this->validPayload());

        $response->assertOk()
            ->assertJsonPath('data.proposal_number', 'P0001')
            ->assertJsonPath('data.primary_discipline', 'general')
            ->assertJsonPath('data.mt_project_no', null)
            ->assertJsonPath('data.revision_no', 0)
            ->assertJsonPath('data.revision_label', 'Rev.0')
            ->assertJsonPath('data.is_latest_revision', true)
            ->assertJsonPath('data.status', 'pending_proposal_review');

        $this->assertDatabaseCount('proposal_contract_review_approvals', 6);
        $this->assertDatabaseHas('proposal_contract_review_approvals', [
            'stage' => 'proposal',
            'approver_code' => 'EMP010',
            'role' => 'MD_DI',
            'decision' => 'pending',
        ]);
        $this->assertDatabaseHas('proposal_contract_review_approvals', [
            'stage' => 'contract',
            'approver_code' => 'EMP012',
            'role' => 'DI',
            'decision' => 'pending',
        ]);
    }

    public function test_create_rejects_invalid_currency_and_duplicate_approvers(): void
    {
        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'currency' => 'JPY',
        ]))->assertStatus(422);

        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'approvers' => [
                ['code' => 'EMP010', 'role' => 'MD_DI'],
                ['code' => 'EMP010', 'role' => 'DI'],
                ['code' => 'EMP011', 'role' => 'DI'],
            ],
        ]))->assertStatus(422);
    }

    public function test_number_series_follow_primary_discipline_mapping(): void
    {
        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'primary_discipline' => 'general',
        ]))->assertJsonPath('data.proposal_number', 'P0001');

        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'project_name' => 'Facade Tower',
            'primary_discipline' => 'facade',
        ]))->assertJsonPath('data.proposal_number', 'FP0001');

        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'project_name' => 'Lighting Mall',
            'primary_discipline' => 'lighting',
        ]))->assertJsonPath('data.proposal_number', 'LP0001');

        $this->postJson('/api/proposal_contract_reviews', $this->validPayload([
            'project_name' => 'Transport Hub',
            'primary_discipline' => 'transportation',
        ]))->assertJsonPath('data.proposal_number', 'TP0001');

        $this->getJson('/api/proposal_contract_reviews/next-number?primary_discipline=facade')
            ->assertOk()
            ->assertJsonPath('data.proposal_number', 'FP0002')
            ->assertJsonPath('data.mt_prefix', 'MFT');
    }

    public function test_proposal_approval_requires_win_probability_and_moves_to_contract_after_three_approvals(): void
    {
        $id = $this->createReview();

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP010',
            'proposal_decision' => 'submitted',
        ])->assertStatus(422);

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP010',
            'proposal_decision' => 'submitted',
            'win_probability' => 70,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_proposal_review')
            ->assertJsonPath('data.win_probability', 70);

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP011',
            'proposal_decision' => 'declined',
        ])->assertStatus(422);

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP011',
            'proposal_decision' => 'submitted',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_proposal_review')
            ->assertJsonPath('data.win_probability', 70);

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP012',
            'proposal_decision' => 'submitted',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_contract_review')
            ->assertJsonPath('data.current_stage', 'contract');
    }

    public function test_proposal_decline_closes_document(): void
    {
        $id = $this->createReview();

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP011',
            'proposal_decision' => 'declined',
            'comment' => 'Fee is too low',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.proposal_decision', 'declined');

        $this->getJson('/api/proposal_contract_reviews/action-items?user_code=EMP012')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_contract_stage_requires_md_decision_generates_mt_number_and_completes(): void
    {
        $id = $this->createReview([
            'primary_discipline' => 'facade',
        ]);
        $this->approveProposal($id);

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP011',
        ])->assertStatus(422);

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP011',
            'contract_decision' => 'proceed',
            'need_quality_plan_pqp' => 'Yes',
        ])->assertStatus(422);

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP010',
            'contract_decision' => 'proceed',
        ])->assertStatus(422);

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP010',
            'contract_decision' => 'proceed',
            'need_quality_plan_pqp' => 'Yes',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_contract_review')
            ->assertJsonPath('data.mt_project_no', 'MFT0001')
            ->assertJsonPath('data.project_no', 'MFT0001')
            ->assertJsonPath('data.projects.0.mt_project_no', 'MFT0001')
            ->assertJsonPath('data.need_quality_plan_pqp', 'Yes');

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP011',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_contract_review');

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP012',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'contract_approved')
            ->assertJsonPath('data.contract_decision', 'proceed');
    }

    public function test_contract_stage_can_create_multiple_mt_projects_for_one_proposal(): void
    {
        $id = $this->createReview();
        $this->approveProposal($id);

        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP010',
            'contract_decision' => 'proceed',
            'need_quality_plan_pqp' => 'No',
            'mt_projects' => [
                ['project_name' => 'Tower A'],
                ['project_name' => 'Tower B'],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.mt_project_no', 'MT0001')
            ->assertJsonPath('data.projects.0.mt_project_no', 'MT0001')
            ->assertJsonPath('data.projects.0.project_name', 'Tower A')
            ->assertJsonPath('data.projects.1.mt_project_no', 'MT0002')
            ->assertJsonPath('data.projects.1.project_name', 'Tower B');

        $this->assertDatabaseHas('postman_proposal_contract_review_projects', [
            'proposal_contract_review_id' => $id,
            'proposal_number' => 'P0001',
            'mt_project_no' => 'MT0001',
            'project_name' => 'Tower A',
        ]);
        $this->assertDatabaseHas('postman_proposal_contract_review_projects', [
            'proposal_contract_review_id' => $id,
            'proposal_number' => 'P0001',
            'mt_project_no' => 'MT0002',
            'project_name' => 'Tower B',
        ]);

        $this->getJson("/api/proposal_contract_reviews/{$id}/projects")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_add_mt_project_after_contract_proceed(): void
    {
        $id = $this->createReview();
        $this->approveProposal($id);
        $this->approveContract($id, 'No');

        $this->postJson("/api/proposal_contract_reviews/{$id}/projects", [
            'project_name' => 'Additional Scope',
        ])->assertOk()
            ->assertJsonPath('data.project.mt_project_no', 'MT0002')
            ->assertJsonPath('data.review.projects.1.project_name', 'Additional Scope');

        $this->assertDatabaseHas('postman_proposal_contract_review_projects', [
            'proposal_contract_review_id' => $id,
            'mt_project_no' => 'MT0002',
            'project_name' => 'Additional Scope',
        ]);
    }

    public function test_action_items_are_stage_specific(): void
    {
        $id = $this->createReview();

        $this->getJson('/api/proposal_contract_reviews/action-items?user_code=EMP010')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action_stage', 'proposal');

        $this->getJson('/api/proposal_contract_reviews/action-items?user_code=EMP999')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->approveProposal($id);

        $this->getJson('/api/proposal_contract_reviews/action-items?user_code=EMP010')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action_stage', 'contract');
    }

    public function test_create_revision_locks_previous_revision_and_restarts_approvals(): void
    {
        $id = $this->createReview();

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP010',
            'proposal_decision' => 'submitted',
            'win_probability' => 65,
        ])->assertStatus(201);

        $response = $this->postJson("/api/proposal_contract_reviews/{$id}/revisions", $this->validPayload([
            'project_name' => 'New Office Tower - Revised Scope',
            'revision_reason' => 'Client changed project scope',
            'revision_summary' => 'Updated project name and reset approval workflow',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.project_name', 'New Office Tower - Revised Scope')
            ->assertJsonPath('data.proposal_number', 'P0001')
            ->assertJsonPath('data.revision_no', 1)
            ->assertJsonPath('data.revision_label', 'Rev.1')
            ->assertJsonPath('data.revision_reason', 'Client changed project scope')
            ->assertJsonPath('data.revised_from_id', $id)
            ->assertJsonPath('data.is_latest_revision', true)
            ->assertJsonPath('data.status', 'pending_proposal_review')
            ->assertJsonPath('data.win_probability', null);

        $newId = (int) $response->json('data.id');
        $this->assertNotSame($id, $newId);

        $this->assertDatabaseHas('postman_proposal_contract_reviews', [
            'id' => $id,
            'revision_no' => 0,
            'is_latest_revision' => false,
        ]);
        $this->assertNotNull(DB::table('postman_proposal_contract_reviews')->where('id', $id)->value('locked_at'));

        $this->assertDatabaseHas('postman_proposal_contract_reviews', [
            'id' => $newId,
            'root_review_id' => $id,
            'revision_no' => 1,
            'revision_label' => 'Rev.1',
            'revised_from_id' => $id,
            'is_latest_revision' => true,
        ]);
        $this->assertDatabaseCount('proposal_contract_review_approvals', 12);

        $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", [
            'approver_code' => 'EMP011',
            'proposal_decision' => 'submitted',
        ])->assertStatus(422);

        $this->getJson('/api/proposal_contract_reviews/action-items?user_code=EMP010')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newId);
    }

    public function test_pqa_plan_can_be_created_from_approved_review_only_when_need_pqp_yes(): void
    {
        $id = $this->createReview([
            'primary_discipline' => 'transportation',
            'project_name' => 'Airport Link',
        ]);

        $this->postJson("/api/project_quality_assurance_plans/from-proposal-contract-review/{$id}")
            ->assertStatus(422);

        $this->approveProposal($id);
        $this->approveContract($id, 'Yes');

        $this->postJson("/api/project_quality_assurance_plans/from-proposal-contract-review/{$id}", [
            'acknowledged_by_vve' => 'VVE Reviewer',
        ])->assertOk()
            ->assertJsonPath('data.proposal_contract_review_id', $id)
            ->assertJsonPath('data.project_name', 'Airport Link')
            ->assertJsonPath('data.project_no', 'TMT0001')
            ->assertJsonPath('data.proposal_number', 'TP0001')
            ->assertJsonPath('data.scope_transport', true);

        $this->assertDatabaseHas('project_quality_assurance_plans', [
            'proposal_contract_review_id' => $id,
            'project_no' => 'TMT0001',
            'proposal_number' => 'TP0001',
        ]);
    }

    private function createReview(array $overrides = []): int
    {
        $response = $this->postJson('/api/proposal_contract_reviews', $this->validPayload($overrides));
        $response->assertOk();

        return (int) $response->json('data.id');
    }

    private function approveProposal(int $id): void
    {
        foreach (['EMP010', 'EMP011', 'EMP012'] as $index => $code) {
            $payload = [
                'approver_code' => $code,
                'proposal_decision' => 'submitted',
            ];

            if ($index === 0) {
                $payload['win_probability'] = 80;
            }

            $this->postJson("/api/proposal_contract_reviews/{$id}/proposal-review", $payload)
                ->assertStatus(201);
        }
    }

    private function approveContract(int $id, string $needPqp): void
    {
        $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
            'approver_code' => 'EMP010',
            'contract_decision' => 'proceed',
            'need_quality_plan_pqp' => $needPqp,
        ])->assertStatus(201);

        foreach (['EMP011', 'EMP012'] as $code) {
            $this->postJson("/api/proposal_contract_reviews/{$id}/contract-review", [
                'approver_code' => $code,
            ])->assertStatus(201);
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'project_name' => 'New Office Tower',
            'proposal_attached' => 'Yes',
            'city' => 'Bangkok',
            'country' => 'Thailand',
            'client_name' => 'ABC Development',
            'client_contact_name' => 'Client Contact',
            'client_address' => '88 Test Road',
            'client_tel' => '021111111',
            'client_fax' => '021111112',
            'architect_name' => 'Design Studio',
            'architect_contact_name' => 'Architect Contact',
            'architect_address' => '99 Design Road',
            'architect_tel' => '022222222',
            'architect_fax' => '022222223',
            'enquiry_from_attached' => 'Yes',
            'project_details' => [
                'concept_detail_design' => true,
            ],
            'disciplines' => [
                'cs' => true,
            ],
            'primary_discipline' => 'general',
            'project_type' => 'COMMERCIAL',
            'fee_calculation_attached' => 'Yes',
            'attachments' => [
                [
                    'type' => 'fee_calculation',
                    'file_path' => '/tmp/fee-calculation.xlsx',
                ],
            ],
            'estimated_total_fees' => 1250000,
            'currency' => 'THB',
            'scope_of_work_clearly_defined' => 'Yes',
            'adequate_staff_resources_available' => 'Yes',
            'help_from_other_offices_required' => 'No',
            'help_from_other_offices_detail' => 'Singapore office for specialist advice',
            'sub_consultants_required' => 'No',
            'sub_consultants_detail' => 'No subcontractor planned',
            'special_quality_assurance_requirement' => 'Client format required',
            'special_consideration' => 'Health and safety plan required',
            'comments' => 'Client expects fast submission and detailed fee basis.',
            'government_project' => 'No',
            'mmcl_project' => 'No',
            'mtl' => 'Yes',
            'filled_in_by' => 'EMP001',
            'approvers' => [
                ['code' => 'EMP010', 'role' => 'MD_DI'],
                ['code' => 'EMP011', 'role' => 'DI'],
                ['code' => 'EMP012', 'role' => 'DI'],
            ],
            'login_id' => 'EMP001',
        ], $overrides);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('project_quality_assurance_plan_documents');
        Schema::dropIfExists('project_quality_assurance_plan_schedules');
        Schema::dropIfExists('project_quality_assurance_plans');
        Schema::dropIfExists('postman_proposal_contract_review_projects');
        Schema::dropIfExists('proposal_contract_review_approvals');
        Schema::dropIfExists('postman_proposal_contract_reviews');
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->string('level_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->boolean('is_approver')->default(true);
            $table->string('active')->default('PER');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->text('detail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('postman_proposal_contract_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('root_review_id')->nullable();
            $table->unsignedInteger('revision_no')->default(0);
            $table->string('revision_label', 50)->nullable();
            $table->text('revision_reason')->nullable();
            $table->text('revision_summary')->nullable();
            $table->unsignedInteger('revised_from_id')->nullable();
            $table->boolean('is_latest_revision')->default(true);
            $table->dateTime('locked_at')->nullable();
            $table->string('project_name')->nullable();
            $table->string('project_no')->nullable();
            $table->string('proposal_number')->nullable();
            $table->string('primary_discipline')->nullable();
            $table->string('mt_project_no')->nullable();
            $table->string('client_name')->nullable();
            $table->string('project_type')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('estimated_total_fees', 18, 2)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('filled_in_by')->nullable();
            $table->string('proposal_to_be_submitted')->nullable();
            $table->string('proposal_decision')->nullable();
            $table->decimal('win_probability', 5, 2)->nullable();
            $table->string('contract_agreed_to_proceed')->nullable();
            $table->string('contract_decision')->nullable();
            $table->string('need_quality_plan_pqp')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('proposal_reviewed_at')->nullable();
            $table->dateTime('contract_reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->longText('payload')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proposal_contract_review_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('proposal_contract_review_id');
            $table->string('stage');
            $table->string('approver_code');
            $table->string('approver_name')->nullable();
            $table->string('approver_email')->nullable();
            $table->string('role');
            $table->unsignedTinyInteger('sequence');
            $table->string('decision')->default('pending');
            $table->decimal('win_probability', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('postman_proposal_contract_review_projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('proposal_contract_review_id');
            $table->string('proposal_number')->nullable();
            $table->string('mt_project_no')->unique();
            $table->string('project_no')->nullable();
            $table->string('project_name')->nullable();
            $table->string('primary_discipline')->nullable();
            $table->string('project_type')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('estimated_total_fees', 18, 2)->nullable();
            $table->unsignedInteger('sequence_no')->default(1);
            $table->string('status')->default('active');
            $table->dateTime('converted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_quality_assurance_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('proposal_contract_review_id')->nullable();
            $table->string('revision')->nullable();
            $table->date('date')->nullable();
            $table->string('prepared_by_tl')->nullable();
            $table->string('approved_by_di')->nullable();
            $table->string('acknowledged_by_vve')->nullable();
            $table->string('project_name')->nullable();
            $table->string('project_no')->nullable();
            $table->string('proposal_number')->nullable();
            $table->string('source_contract_decision')->nullable();
            $table->boolean('scope_cs')->nullable();
            $table->boolean('scope_me')->nullable();
            $table->boolean('scope_leed_esd')->nullable();
            $table->boolean('scope_facade')->nullable();
            $table->boolean('scope_lighting')->nullable();
            $table->boolean('scope_pm')->nullable();
            $table->boolean('scope_cm')->nullable();
            $table->boolean('scope_transport')->nullable();
            $table->boolean('scope_geotechnical')->nullable();
            $table->boolean('scope_qs')->nullable();
            $table->boolean('scope_engineering_audit')->nullable();
            $table->boolean('scope_others_flag')->nullable();
            $table->string('scope_others_text')->nullable();
            $table->string('team_di')->nullable();
            $table->string('team_tl')->nullable();
            $table->string('team_pm')->nullable();
            $table->string('team_bm')->nullable();
            $table->string('team_cm')->nullable();
            $table->string('team_re')->nullable();
            $table->string('coord_cs')->nullable();
            $table->string('coord_facade')->nullable();
            $table->string('coord_others')->nullable();
            $table->string('coord_me')->nullable();
            $table->string('coord_lighting')->nullable();
            $table->string('coord_leed_esd')->nullable();
            $table->string('coord_transport')->nullable();
            $table->string('coord_bco')->nullable();
            $table->boolean('validation_before_docs_issued')->nullable();
            $table->boolean('validation_within_14days_after_docs')->nullable();
            $table->string('status')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_quality_assurance_plan_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_quality_assurance_plan_id');
            $table->string('item_key')->nullable();
            $table->string('item')->nullable();
            $table->dateTime('proposed_schedule')->nullable();
            $table->boolean('review_required_cs')->default(false);
            $table->boolean('review_required_mep')->default(false);
            $table->string('reviewer_cs')->nullable();
            $table->string('reviewer_mep')->nullable();
            $table->string('initial_cs')->nullable();
            $table->string('initial_mep')->nullable();
            $table->dateTime('review_date')->nullable();
            $table->timestamps();
        });

        Schema::create('project_quality_assurance_plan_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_quality_assurance_plan_id');
            $table->string('document')->nullable();
            $table->string('detail')->nullable();
            $table->boolean('required')->default(false);
            $table->string('completion_stage')->nullable();
            $table->string('responsible_personnel')->nullable();
            $table->timestamps();
        });
    }

    private function seedEmployees(): void
    {
        DB::table('employees')->insert([
            [
                'code' => 'EMP001',
                'firstname' => 'Filled',
                'lastname' => 'User',
                'email' => 'filled.user@example.test',
                'level_name' => 'Staff',
                'title_name' => 'Engineer',
                'department_name' => 'C&S',
                'employee_type_name' => 'PER',
                'active' => 'PER',
                'is_approver' => true,
            ],
            [
                'code' => 'EMP010',
                'firstname' => 'Managing',
                'lastname' => 'Director',
                'email' => 'md@example.test',
                'level_name' => 'MD/DI',
                'title_name' => 'Director',
                'department_name' => 'Management',
                'employee_type_name' => 'PER',
                'active' => 'PER',
                'is_approver' => true,
            ],
            [
                'code' => 'EMP011',
                'firstname' => 'Discipline',
                'lastname' => 'One',
                'email' => 'di.one@example.test',
                'level_name' => 'DI',
                'title_name' => 'Director',
                'department_name' => 'C&S',
                'employee_type_name' => 'PER',
                'active' => 'PER',
                'is_approver' => true,
            ],
            [
                'code' => 'EMP012',
                'firstname' => 'Discipline',
                'lastname' => 'Two',
                'email' => 'di.two@example.test',
                'level_name' => 'DI',
                'title_name' => 'Director',
                'department_name' => 'M&E',
                'employee_type_name' => 'PER',
                'active' => 'PER',
                'is_approver' => true,
            ],
        ]);
    }

    private function seedProjectTypes(): void
    {
        DB::table('project_types')->insert([
            [
                'code' => 'COMMERCIAL',
                'name' => 'Commercial',
                'detail' => 'Commercial building',
                'is_active' => true,
            ],
            [
                'code' => 'RESIDENTIAL',
                'name' => 'Residential',
                'detail' => 'Residential project',
                'is_active' => true,
            ],
        ]);
    }
}
