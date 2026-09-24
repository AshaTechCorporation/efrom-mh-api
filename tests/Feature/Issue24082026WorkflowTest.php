<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Issue24082026WorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createWorkflowTables();
        $this->createProjectQualityPlanTables();
    }

    public function test_accounts_ca_steps_are_server_authorized_and_sequential_for_all_three_forms(): void
    {
        $cases = [
            [
                'table' => 'charitable_contributions',
                'route' => 'charitable_contributions',
                'steps' => [
                    ['type' => 'acsc_by_status', 'by' => 'acsc_by', 'status' => 'acsc_by_status', 'actor' => 'VERIFY-01'],
                    ['type' => 'ims_acknowledged_by_status', 'by' => 'ims_acknowledged_by', 'status' => 'ims_acknowledged_by_status', 'actor' => 'ACSL-01'],
                    ['type' => 'approver_by_status', 'by' => 'approver_by', 'status' => 'approver_by_status', 'actor' => 'APPROVE-01'],
                    ['type' => 'approver_by_2_status', 'by' => 'approver_by_2', 'status' => 'approver_by_2_status', 'actor' => 'APPROVE-01-B'],
                    ['type' => 'acsl_by_status', 'by' => 'acsl_by', 'status' => 'acsl_by_status', 'actor' => 'CA-01'],
                ],
            ],
            [
                'table' => 'gift_hospitalities',
                'route' => 'gift_hospitalities',
                'steps' => [
                    ['type' => 'verified_by_status', 'by' => 'verified_by', 'status' => 'verified_by_status', 'actor' => 'VERIFY-02'],
                    ['type' => 'approved_by_status', 'by' => 'approved_by', 'status' => 'approved_by_status', 'actor' => 'APPROVE-02'],
                    ['type' => 'ims_acknowledged_by_status', 'by' => 'ims_acknowledged_by', 'status' => 'ims_acknowledged_by_status', 'actor' => 'IMS-02'],
                    ['type' => 'acknowledged_by_status', 'by' => 'acknowledged_by', 'status' => 'acknowledged_by_status', 'actor' => 'CA-02'],
                ],
            ],
            [
                'table' => 'gift_hospitality_offerings',
                'route' => 'gift_hospitality_offerings',
                'steps' => [
                    ['type' => 'verified_by_status', 'by' => 'verified_by', 'status' => 'verified_by_status', 'actor' => 'VERIFY-03'],
                    ['type' => 'ims_acknowledged_by_status', 'by' => 'ims_acknowledged_by', 'status' => 'ims_acknowledged_by_status', 'actor' => 'ACSL-03'],
                    ['type' => 'approved_by_status', 'by' => 'approved_by', 'status' => 'approved_by_status', 'actor' => 'APPROVE-03'],
                    ['type' => 'approved_by_2_status', 'by' => 'approved_by_2', 'status' => 'approved_by_2_status', 'actor' => 'APPROVE-03-B'],
                    ['type' => 'acknowledged_by_status', 'by' => 'acknowledged_by', 'status' => 'acknowledged_by_status', 'actor' => 'CA-03'],
                ],
            ],
        ];

        foreach ($cases as $index => $case) {
            $row = [
                'created_at' => now(),
                'updated_at' => now(),
            ];
            foreach ($case['steps'] as $step) {
                $row[$step['by']] = $step['actor'];
                $row[$step['status']] = 'pending';
            }
            $id = DB::table($case['table'])->insertGetId($row);

            $caStep = $case['steps'][count($case['steps']) - 1];
            $this->withWorkflowActor($caStep['actor'])
                ->patchJson("/api/{$case['route']}/{$id}/actions/{$caStep['type']}", ['decision' => 'approved'])
                ->assertStatus(409)
                ->assertJsonPath('status', false);

            $verifyStep = $case['steps'][0];
            $this->withWorkflowActor('WRONG-' . $index)
                ->patchJson("/api/{$case['route']}/{$id}/actions/{$verifyStep['type']}", ['decision' => 'approved'])
                ->assertStatus(403)
                ->assertJsonPath('status', false);

            $this->withWorkflowActor($verifyStep['actor'])
                ->patchJson("/api/{$case['route']}/{$id}/actions/{$verifyStep['type']}", ['decision' => 'approved'])
                ->assertStatus(201)
                ->assertJsonPath("data.{$verifyStep['status']}", 'approved');

            $this->withWorkflowActor($caStep['actor'])
                ->patchJson("/api/{$case['route']}/{$id}/actions/{$caStep['type']}", ['decision' => 'approved'])
                ->assertStatus(409)
                ->assertJsonPath('status', false);

            foreach (array_slice($case['steps'], 1) as $step) {
                $this->withWorkflowActor($step['actor'])
                    ->patchJson("/api/{$case['route']}/{$id}/actions/{$step['type']}", ['decision' => 'approved'])
                    ->assertStatus(201)
                    ->assertJsonPath('status', true)
                    ->assertJsonPath("data.{$step['status']}", 'approved');

                $this->assertDatabaseHas($case['table'], [
                    'id' => $id,
                    $step['status'] => 'approved',
                    'update_by' => $step['actor'],
                ]);
            }
        }
    }

    public function test_workflow_action_requires_authentication(): void
    {
        $id = DB::table('gift_hospitalities')->insertGetId([
            'verified_by' => 'VERIFY-02',
            'verified_by_status' => 'pending',
            'acknowledged_by' => 'CA-02',
            'acknowledged_by_status' => 'pending',
            'approved_by' => 'APPROVE-02',
            'approved_by_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson("/api/gift_hospitalities/{$id}/actions/verified_by_status", ['decision' => 'approved'])
            ->assertStatus(401)
            ->assertJsonPath('status', false);
    }

    public function test_legacy_document_without_ims_assignee_cannot_reach_accounts_ca(): void
    {
        $id = DB::table('gift_hospitalities')->insertGetId([
            'verified_by' => 'VERIFY-LEGACY',
            'verified_by_status' => 'approved',
            'approved_by' => 'APPROVE-LEGACY',
            'approved_by_status' => 'approved',
            'ims_acknowledged_by' => null,
            'ims_acknowledged_by_status' => null,
            'acknowledged_by' => 'CA-LEGACY',
            'acknowledged_by_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withWorkflowActor('CA-LEGACY')
            ->patchJson("/api/gift_hospitalities/{$id}/actions/acknowledged_by_status", ['decision' => 'approved'])
            ->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_completed_legacy_document_without_ims_remains_completed(): void
    {
        $id = DB::table('gift_hospitality_offerings')->insertGetId([
            'verified_by' => 'VERIFY-COMPLETE',
            'verified_by_status' => 'approved',
            'approved_by' => 'APPROVE-COMPLETE',
            'approved_by_status' => 'approved',
            'approved_by_2' => null,
            'approved_by_2_status' => null,
            'ims_acknowledged_by' => null,
            'ims_acknowledged_by_status' => null,
            'acknowledged_by' => 'CA-COMPLETE',
            'acknowledged_by_status' => 'approved',
            'status' => null,
            'create_by' => 'REQUESTER-COMPLETE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withWorkflowActor('CA-COMPLETE')
            ->patchJson("/api/gift_hospitality_offerings/{$id}/actions/acknowledged_by_status", ['decision' => 'approved'])
            ->assertStatus(409)
            ->assertJsonPath('status', false);

        $this->getJson('/api/dashboard/personal-summary?user_code=REQUESTER-COMPLETE')
            ->assertOk()
            ->assertJsonPath('data.recentRequests.0.status', 'completed')
            ->assertJsonPath('data.recentRequests.0.currentStep', 'Completed');
    }

    public function test_dashboard_skips_unassigned_optional_approval_steps(): void
    {
        $completedId = DB::table('gift_hospitality_offerings')->insertGetId([
            'verified_by' => 'VERIFY-03',
            'verified_by_status' => 'approved',
            'acknowledged_by' => 'CA-03',
            'acknowledged_by_status' => 'approved',
            'approved_by' => 'APPROVE-03',
            'approved_by_status' => 'approved',
            'approved_by_2' => null,
            'approved_by_2_status' => null,
            'ims_acknowledged_by' => 'ACSL-03',
            'ims_acknowledged_by_status' => 'approved',
            'create_by' => 'REQUESTER-03',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $pendingId = DB::table('gift_hospitality_offerings')->insertGetId([
            'verified_by' => 'VERIFY-04',
            'verified_by_status' => 'approved',
            'acknowledged_by' => 'CA-04',
            'acknowledged_by_status' => 'approved',
            'approved_by' => 'APPROVE-04',
            'approved_by_status' => 'approved',
            'approved_by_2' => 'APPROVE-SECOND-04',
            'approved_by_2_status' => 'pending',
            'ims_acknowledged_by' => 'ACSL-04',
            'ims_acknowledged_by_status' => 'pending',
            'create_by' => 'REQUESTER-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/dashboard/personal-summary?user_code=REQUESTER-03');
        $response->assertOk()
            ->assertJsonPath('data.summary.my_requests_count', 2)
            ->assertJsonPath('data.summary.pending_count', 1)
            ->assertJsonPath('data.summary.completed_this_month_count', 1);

        $records = collect($response->json('data.recentRequests'))->keyBy('id');
        $this->assertSame('completed', $records[(string) $completedId]['status']);
        $this->assertSame('Completed', $records[(string) $completedId]['currentStep']);
        $this->assertSame('pending', $records[(string) $pendingId]['status']);
        $this->assertSame('ACSL acknowledge', $records[(string) $pendingId]['currentStep']);
    }

    public function test_project_quality_plan_write_routes_require_authentication(): void
    {
        $this->postJson('/api/project_quality_assurance_plans', [])->assertStatus(401);
        $this->putJson('/api/project_quality_assurance_plans/999', [])->assertStatus(401);
        $this->deleteJson('/api/project_quality_assurance_plans/999')->assertStatus(401);
        $this->postJson('/api/project_quality_assurance_plans/from-proposal-contract-review/999', [])->assertStatus(401);
    }

    public function test_project_quality_plan_returns_field_errors_instead_of_database_error(): void
    {
        $this->withWorkflowActor('PQP-TEST')
            ->postJson('/api/project_quality_assurance_plans', [])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors([
                'revision',
                'date',
                'prepared_by_tl',
                'approved_by_di',
                'acknowledged_by_vve',
                'project_name',
                'project_no',
            ]);
    }

    public function test_project_quality_plan_can_be_submitted_with_complete_visible_fields(): void
    {
        $payload = [
            'revision' => 'Rev. 01',
            'date' => '2026-08-25',
            'prepared_by_tl' => 'TL, Test Lead',
            'approved_by_di' => 'DI, Test Director',
            'acknowledged_by_vve' => 'VVE, Test Reviewer',
            'project_name' => 'Issue 24082026 Verification Project',
            'project_no' => 'TEST-PQP-001',
            'scope_cs' => true,
            'quality_plan_schedule' => [],
            'documents_required' => [],
        ];

        $this->withWorkflowActor('PQP-TEST')
            ->postJson('/api/project_quality_assurance_plans', $payload)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.revision', 'Rev. 01')
            ->assertJsonPath('data.project_no', 'TEST-PQP-001');

        $this->assertDatabaseHas('project_quality_assurance_plans', [
            'revision' => 'Rev. 01',
            'project_no' => 'TEST-PQP-001',
            'scope_cs' => 1,
        ]);
    }

    private function withWorkflowActor(string $employeeCode): self
    {
        $now = time();
        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 1,
            'lun' => (object) [
                'id' => 1,
                'username' => strtolower($employeeCode),
                'employee_code' => $employeeCode,
            ],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], 'key');

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function createWorkflowTables(): void
    {
        Schema::create('charitable_contributions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('acsc_by')->nullable();
            $table->string('acsc_by_status')->nullable();
            $table->dateTime('acsc_by_date')->nullable();
            $table->string('acsl_by')->nullable();
            $table->string('acsl_by_status')->nullable();
            $table->dateTime('acsl_by_date')->nullable();
            $table->string('approver_by')->nullable();
            $table->string('approver_by_status')->nullable();
            $table->dateTime('approver_by_date')->nullable();
            $table->string('approver_by_2')->nullable();
            $table->string('approver_by_2_status')->nullable();
            $table->dateTime('approver_by_2_date')->nullable();
            $table->string('ims_acknowledged_by')->nullable();
            $table->string('ims_acknowledged_by_status')->nullable();
            $table->dateTime('ims_acknowledged_by_date')->nullable();
            $table->string('status')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_hospitalities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('verified_by')->nullable();
            $table->string('verified_by_status')->nullable();
            $table->dateTime('verified_by_date')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_status')->nullable();
            $table->dateTime('approved_by_date')->nullable();
            $table->string('ims_acknowledged_by')->nullable();
            $table->string('ims_acknowledged_by_status')->nullable();
            $table->dateTime('ims_acknowledged_by_date')->nullable();
            $table->string('status')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_hospitality_offerings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('verified_by')->nullable();
            $table->string('verified_by_status')->nullable();
            $table->dateTime('verified_by_date')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_status')->nullable();
            $table->dateTime('approved_by_date')->nullable();
            $table->string('approved_by_2')->nullable();
            $table->string('approved_by_2_status')->nullable();
            $table->dateTime('approved_by_2_date')->nullable();
            $table->string('ims_acknowledged_by')->nullable();
            $table->string('ims_acknowledged_by_status')->nullable();
            $table->dateTime('ims_acknowledged_by_date')->nullable();
            $table->string('status')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createProjectQualityPlanTables(): void
    {
        Schema::create('project_quality_assurance_plans', function (Blueprint $table) {
            $table->increments('id');
            foreach ([
                'revision', 'prepared_by_tl', 'approved_by_di', 'acknowledged_by_vve',
                'project_name', 'project_no', 'proposal_number', 'source_contract_decision',
                'scope_others_text', 'team_di', 'team_tl', 'team_pm', 'team_bm', 'team_cm',
                'team_re', 'coord_cs', 'coord_facade', 'coord_others', 'coord_me',
                'coord_lighting', 'coord_leed_esd', 'coord_transport', 'coord_bco',
                'status', 'create_by', 'update_by',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->unsignedInteger('proposal_contract_review_id')->nullable();
            $table->date('date')->nullable();
            foreach ([
                'scope_cs', 'scope_me', 'scope_leed_esd', 'scope_facade', 'scope_lighting',
                'scope_pm', 'scope_cm', 'scope_transport', 'scope_geotechnical', 'scope_qs',
                'scope_engineering_audit', 'scope_others_flag', 'validation_before_docs_issued',
                'validation_within_14days_after_docs',
            ] as $column) {
                $table->boolean($column)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_quality_assurance_plan_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_quality_assurance_plan_id');
            $table->string('item_key')->nullable();
            $table->string('item')->nullable();
            $table->date('proposed_schedule')->nullable();
            $table->boolean('review_required_cs')->nullable();
            $table->boolean('review_required_mep')->nullable();
            $table->string('reviewer_cs')->nullable();
            $table->string('reviewer_mep')->nullable();
            $table->string('initial_cs')->nullable();
            $table->string('initial_mep')->nullable();
            $table->date('review_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_quality_assurance_plan_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_quality_assurance_plan_id');
            $table->string('document')->nullable();
            $table->text('detail')->nullable();
            $table->boolean('required')->nullable();
            $table->string('completion_stage')->nullable();
            $table->string('responsible_personnel')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
