<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeeSheetRevisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTables();
    }

    public function test_project_fee_sheet_uses_full_page_revision_snapshots(): void
    {
        $create = $this->postJson('/api/fee-sheets', $this->payload([
            'project_name' => 'Original Tower',
            'mt_project_no' => 'MT001',
            'fee_agreements' => [
                $this->feeAgreement(1000000),
            ],
            'job_costing' => [
                $this->jobCosting('P', 40),
                $this->jobCosting('D', 60),
            ],
            'billing_forecast' => [
                $this->billingForecast('2026-06-01', 250000),
            ],
        ]));

        $create->assertOk()
            ->assertJsonPath('revision_no', 0);

        $feeSheetId = $create->json('fee_sheet_id');
        $this->assertDatabaseCount('fee_sheet_revisions', 1);
        $this->assertDatabaseHas('fee_sheet_revisions', [
            'fee_sheet_id' => $feeSheetId,
            'rev_no' => 0,
            'is_latest' => 1,
            'project_name' => 'Original Tower',
            'mt_project_no' => 'MT001',
        ]);

        $edit = $this->putJson("/api/fee-sheets/{$feeSheetId}", $this->payload([
            'mode' => 'edit_current',
            'project_name' => 'Original Tower Edited',
            'mt_project_no' => 'MT001-A',
            'fee_agreements' => [
                $this->feeAgreement(1100000),
            ],
        ]));

        $edit->assertOk()
            ->assertJsonPath('revision_no', 0)
            ->assertJsonPath('mode', 'edit_current');

        $this->assertDatabaseCount('fee_sheet_revisions', 1);
        $this->assertDatabaseHas('fee_sheet_revisions', [
            'fee_sheet_id' => $feeSheetId,
            'rev_no' => 0,
            'project_name' => 'Original Tower Edited',
            'mt_project_no' => 'MT001-A',
        ]);

        $revision = $this->postJson("/api/fee-sheets/{$feeSheetId}/revisions", $this->payload([
            'project_name' => 'Revised Tower',
            'mt_project_no' => 'MT001-R1',
            'client_name' => 'Revised Client',
            'fee_agreements' => [
                $this->feeAgreement(1250000),
            ],
            'job_costing' => [
                $this->jobCosting('P', 30),
                $this->jobCosting('D', 70),
            ],
            'billing_forecast' => [
                $this->billingForecast('2026-07-01', 300000),
            ],
        ]));

        $revision->assertOk()
            ->assertJsonPath('revision_no', 1);

        $this->assertDatabaseCount('fee_sheet_revisions', 2);
        $this->assertDatabaseHas('fee_sheet_revisions', [
            'fee_sheet_id' => $feeSheetId,
            'rev_no' => 0,
            'is_latest' => 0,
            'project_name' => 'Original Tower Edited',
        ]);
        $this->assertDatabaseHas('fee_sheet_revisions', [
            'fee_sheet_id' => $feeSheetId,
            'rev_no' => 1,
            'is_latest' => 1,
            'status' => 'draft',
            'project_name' => 'Revised Tower',
            'mt_project_no' => 'MT001-R1',
        ]);

        $show = $this->getJson("/api/fee_sheets/{$feeSheetId}");

        $show->assertOk()
            ->assertJsonCount(2, 'data.revisions')
            ->assertJsonPath('data.current_revision.rev_no', 1)
            ->assertJsonPath('data.current_revision.project_name', 'Revised Tower')
            ->assertJsonPath('data.revisions.0.rev_no', 0)
            ->assertJsonPath('data.revisions.0.project_name', 'Original Tower Edited')
            ->assertJsonPath('data.revisions.1.fee_agreements.0.gross_fee_excl_vat', 1250000)
            ->assertJsonPath('data.revisions.1.job_costings.0.revision_no', 0)
            ->assertJsonPath('data.revisions.1.billing_forecasts.0.revision_no', 0);
    }

    public function test_show_normalizes_old_section_level_revisions_to_latest_values(): void
    {
        $create = $this->postJson('/api/fee-sheets', $this->payload([
            'fee_agreements' => [
                $this->feeAgreement(1000000, 0),
                $this->feeAgreement(1500000, 1, [
                    'less_subconsultants_name' => 'Legacy Sub',
                    'less_subconsultants_number' => 25000,
                    'net_fee_excl_vat' => 1475000,
                ]),
            ],
            'job_costing' => [
                $this->jobCosting('P', 20, 0),
                $this->jobCosting('P', 45, 1),
            ],
            'billing_forecast' => [
                $this->billingForecast('2026-06-01', 100000, 0),
                $this->billingForecast('2026-07-01', 200000, 1),
            ],
        ]));

        $feeSheetId = $create->json('fee_sheet_id');

        $show = $this->getJson("/api/fee_sheets/{$feeSheetId}");

        $show->assertOk()
            ->assertJsonCount(1, 'data.revisions')
            ->assertJsonCount(1, 'data.revisions.0.fee_agreements')
            ->assertJsonPath('data.revisions.0.fee_agreements.0.gross_fee_excl_vat', 1500000)
            ->assertJsonPath('data.revisions.0.fee_agreements.0.revision_no', 0)
            ->assertJsonPath('data.revisions.0.fee_agreements.0.less_subconsultants_items.0.name', 'Legacy Sub')
            ->assertJsonPath('data.revisions.0.fee_agreements.0.less_subconsultants_items.0.amount', 25000)
            ->assertJsonPath('data.revisions.0.job_costings.0.percent', 45)
            ->assertJsonPath('data.revisions.0.job_costings.0.revision_no', 0)
            ->assertJsonPath('data.revisions.0.billing_forecasts.0.month', '2026-07-01')
            ->assertJsonPath('data.revisions.0.billing_forecasts.0.revision_no', 0);
    }

    public function test_project_fee_sheet_persists_fee_agreement_line_items_and_aggregates(): void
    {
        $create = $this->postJson('/api/fee-sheets', $this->payload([
            'fee_agreements' => [
                $this->feeAgreement(1000000, 0, [
                    'less_subconsultants_items' => [
                        ['name' => 'Sub A', 'amount' => 100000],
                        ['name' => 'Sub B', 'amount' => 50000],
                    ],
                    'less_other_expenses_items' => [
                        ['name' => 'Printing', 'amount' => 10000],
                        ['name' => 'Travel', 'amount' => 15000],
                    ],
                    'net_fee_excl_vat' => 825000,
                ]),
            ],
        ]));

        $create->assertOk();
        $feeSheetId = $create->json('fee_sheet_id');
        $agreement = DB::table('fee_agreements')->first();

        $this->assertNotNull($agreement);
        $this->assertSame('Sub A, Sub B', $agreement->less_subconsultants_name);
        $this->assertSame(150000.0, (float) $agreement->less_subconsultants_number);
        $this->assertSame('Printing, Travel', $agreement->less_other_expenses_name);
        $this->assertSame(25000.0, (float) $agreement->less_other_expenses);
        $this->assertDatabaseCount('fee_agreement_line_items', 4);
        $this->assertDatabaseHas('fee_agreement_line_items', [
            'fee_agreement_id' => $agreement->id,
            'category' => 'subconsultant',
            'name' => 'Sub A',
            'sort_order' => 0,
        ]);

        $revision = $this->postJson("/api/fee-sheets/{$feeSheetId}/revisions", $this->payload([
            'fee_agreements' => [
                $this->feeAgreement(1200000, 0, [
                    'less_subconsultants_items' => [
                        ['name' => 'Sub C', 'amount' => 200000],
                    ],
                    'less_other_expenses_items' => [
                        ['name' => 'Travel', 'amount' => 5000],
                    ],
                    'net_fee_excl_vat' => 995000,
                ]),
            ],
        ]));

        $revision->assertOk()
            ->assertJsonPath('revision_no', 1);

        $show = $this->getJson("/api/fee_sheets/{$feeSheetId}");
        $show->assertOk()
            ->assertJsonPath('data.revisions.0.fee_agreements.0.less_subconsultants_items.1.name', 'Sub B')
            ->assertJsonPath('data.revisions.0.fee_agreements.0.less_subconsultants_number', 150000)
            ->assertJsonPath('data.revisions.1.fee_agreements.0.less_subconsultants_items.0.name', 'Sub C')
            ->assertJsonPath('data.revisions.1.fee_agreements.0.less_other_expenses_items.0.amount', 5000);
    }

    public function test_project_fee_sheet_persists_fee_agreement_received_text(): void
    {
        $create = $this->postJson('/api/fee-sheets', $this->payload([
            'fee_agreements' => [
                $this->feeAgreement(1000000, 0, [
                    'agreement_received' => 'Agreement pending client signature',
                ]),
            ],
        ]));

        $create->assertOk();
        $feeSheetId = $create->json('fee_sheet_id');

        $this->assertDatabaseHas('fee_agreements', [
            'agreement_received' => 'Agreement pending client signature',
        ]);

        $show = $this->getJson("/api/fee_sheets/{$feeSheetId}");
        $show->assertOk()
            ->assertJsonPath(
                'data.revisions.0.fee_agreements.0.agreement_received',
                'Agreement pending client signature'
            );
    }

    public function test_index_includes_current_revision_fee_agreement_amounts(): void
    {
        $create = $this->postJson('/api/fee-sheets', $this->payload([
            'fee_sheet_type' => 'transportation',
            'fee_agreements' => [
                $this->feeAgreement(1000, 0, [
                    'less_subconsultants_number' => 10,
                    'less_other_expenses' => 100,
                    'net_fee_excl_vat' => 890,
                ]),
            ],
        ]));

        $create->assertOk();

        $index = $this->getJson('/api/get_fee-sheets?fee_sheet_type=transportation');

        $index->assertOk()
            ->assertJsonPath('data.0.current_revision.fee_agreements.0.gross_fee_excl_vat', 1000)
            ->assertJsonPath('data.0.current_revision.fee_agreements.0.net_fee_excl_vat', 890);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'fee_sheet_type' => 'project',
            'project_id' => null,
            'proposal_project_reference_id' => null,
            'mt_project_no' => 'MT001',
            'project_name' => 'Project Fee Sheet',
            'discipline_id' => 1,
            'director_in_charge_id' => 1,
            'client_name' => 'Client',
            'location' => 'Bangkok',
            'mtl_scope_detail' => 'Scope',
            'contact_name' => 'Contact',
            'comment' => 'Comment',
            'project_type_id' => 1,
            'form_filled_by_id' => 'EMP001',
            'form_filled_by_date' => '2026-05-28',
            'approved_by_ch_id' => 'EMP002',
            'approved_by_ch_date' => '2026-05-28',
            'team' => ['EMP001', 'EMP002'],
            'fee_agreements' => [
                $this->feeAgreement(1000000),
            ],
            'job_costing' => [
                $this->jobCosting('P', 40),
                $this->jobCosting('D', 60),
            ],
            'billing_forecast' => [
                $this->billingForecast('2026-06-01', 250000),
            ],
        ], $overrides);
    }

    private function feeAgreement(int $grossFee, int $revisionNo = 0, array $overrides = []): array
    {
        return array_merge([
            'revision_no' => $revisionNo,
            'revision_label' => $revisionNo === 0 ? 'Original' : "Rev {$revisionNo}",
            'revision_name' => $revisionNo === 0 ? 'Original' : "Rev {$revisionNo}",
            'gross_fee_excl_vat' => $grossFee,
            'less_subconsultants_name' => null,
            'less_subconsultants_number' => 0,
            'less_other_expenses_name' => null,
            'less_other_expenses' => 0,
            'net_fee_excl_vat' => $grossFee,
            'agreement_received' => null,
        ], $overrides);
    }

    private function jobCosting(string $phase, int $percent, int $revisionNo = 0): array
    {
        return [
            'revision_no' => $revisionNo,
            'revision_label' => $revisionNo === 0 ? 'Original' : "Rev {$revisionNo}",
            'phase' => $phase,
            'percent' => $percent,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
        ];
    }

    private function billingForecast(string $month, int $amount, int $revisionNo = 0): array
    {
        return [
            'revision_no' => $revisionNo,
            'revision_label' => $revisionNo === 0 ? 'Original' : "Rev {$revisionNo}",
            'month' => $month,
            'amount' => $amount,
        ];
    }

    private function createTables(): void
    {
        Schema::create('fee_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('mt_project_no')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('proposal_project_reference_id')->nullable();
            $table->unsignedInteger('current_revision_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_sheet_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('fee_sheet_id');
            $table->integer('rev_no');
            $table->boolean('is_latest')->default(true);
            $table->string('fee_sheet_type')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('proposal_project_reference_id')->nullable();
            $table->string('mt_project_no')->nullable();
            $table->string('project_name')->nullable();
            $table->unsignedInteger('discipline_id')->nullable();
            $table->unsignedInteger('director_in_charge_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('location')->nullable();
            $table->text('mtl_scope_detail')->nullable();
            $table->string('contact_name')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('project_type_id')->nullable();
            $table->string('form_filled_by_id')->nullable();
            $table->date('form_filled_by_date')->nullable();
            $table->string('approved_by_ch_id')->nullable();
            $table->date('approved_by_ch_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_sheet_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision_id');
            $table->string('employee_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_agreements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision_id')->nullable();
            $table->integer('revision_no')->default(0);
            $table->string('revision_label')->nullable();
            $table->string('revision_name')->nullable();
            $table->decimal('gross_fee_excl_vat', 15, 2)->nullable();
            $table->string('less_subconsultants_name')->nullable();
            $table->decimal('less_subconsultants_number', 15, 2)->nullable();
            $table->string('less_other_expenses_name')->nullable();
            $table->decimal('less_other_expenses', 15, 2)->nullable();
            $table->decimal('net_fee_excl_vat', 15, 2)->nullable();
            $table->string('agreement_received')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_agreement_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('fee_agreement_id');
            $table->string('category')->nullable();
            $table->string('name')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_costings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision_id')->nullable();
            $table->integer('revision_no')->default(0);
            $table->string('revision_label')->nullable();
            $table->string('phase')->nullable();
            $table->integer('percent')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision_id')->nullable();
            $table->integer('revision_no')->default(0);
            $table->string('revision_label')->nullable();
            $table->date('month')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proposal_project_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('proposal_contract_review_id')->nullable();
            $table->unsignedInteger('proposal_contract_review_project_id')->nullable();
            $table->string('proposal_number')->nullable();
            $table->string('project_number')->nullable();
            $table->string('project_name')->nullable();
            $table->string('status')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proposal_contract_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('project_no')->nullable();
            $table->string('project_name')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('city')->nullable();
            $table->string('copies_to')->nullable();
            $table->string('circ_adm')->nullable();
            $table->string('ch_file')->nullable();
            $table->decimal('estimated_total_fees', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
