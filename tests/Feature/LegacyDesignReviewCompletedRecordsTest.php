<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyDesignReviewCompletedRecordsTest extends TestCase
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

        Schema::create('legacy_design_review_sync_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source_system', 50);
            $table->string('source_database', 100);
            $table->string('source_stage', 100);
            $table->string('source_table', 100);
            $table->string('source_id', 100);
            $table->string('project_no', 100)->nullable();
            $table->string('project_name', 255)->nullable();
            $table->string('discipline', 255)->nullable();
            $table->integer('legacy_status_code')->nullable();
            $table->string('legacy_status_label', 100)->nullable();
            $table->string('target_module', 100)->nullable();
            $table->string('target_table', 100)->nullable();
            $table->string('target_route', 150)->nullable();
            $table->string('sync_status', 50)->default('synced');
            $table->string('user_mapping_status', 50)->default('pending');
            $table->string('generate_status', 50)->default('pending');
            $table->unsignedBigInteger('generated_id')->nullable();
            $table->string('generated_table', 100)->nullable();
            $table->text('raw_payload')->nullable();
            $table->text('mapped_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_completed_records_page_returns_only_completed_legacy_rows(): void
    {
        $this->insertSyncRecord([
            'source_stage' => 'design-criteria-report',
            'source_id' => '101',
            'project_no' => 'P-100',
            'project_name' => 'Alpha Tower',
            'legacy_status_code' => 6,
            'legacy_status_label' => 'Completed',
            'target_module' => 'concept_design_review',
            'generated_id' => 11,
            'generate_status' => 'generated',
        ]);
        $this->insertSyncRecord([
            'source_stage' => 'tender-design-review',
            'source_id' => '202',
            'project_no' => 'P-200',
            'project_name' => 'Beta Plaza',
            'legacy_status_code' => null,
            'legacy_status_label' => 'Completed',
            'target_module' => 'tender_mep_review',
        ]);
        $this->insertSyncRecord([
            'source_stage' => 'submission',
            'source_id' => '303',
            'project_no' => 'P-300',
            'project_name' => 'Gamma Mall',
            'legacy_status_code' => 1,
            'legacy_status_label' => 'VVE to review',
            'target_module' => 'submission_review',
        ]);

        $response = $this->postJson('/api/legacy-design-review/completed-records/page', [
            'draw' => 1,
            'columns' => [],
            'order' => [['column' => 8, 'dir' => 'desc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.recordsTotal', 2)
            ->assertJsonPath('data.recordsFiltered', 2)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.legacyStatusLabel', 'Completed');
    }

    public function test_completed_records_page_filters_by_type_and_search(): void
    {
        $this->insertSyncRecord([
            'source_stage' => 'design-criteria-report',
            'source_id' => '101',
            'project_no' => 'P-100',
            'project_name' => 'Alpha Tower',
            'legacy_status_code' => 6,
            'legacy_status_label' => 'Completed',
            'target_module' => 'concept_design_review',
        ]);
        $this->insertSyncRecord([
            'source_stage' => 'tender-design-review',
            'source_id' => '202',
            'project_no' => 'P-200',
            'project_name' => 'Beta Plaza',
            'legacy_status_code' => 6,
            'legacy_status_label' => 'Completed',
            'target_module' => 'tender_mep_review',
        ]);

        $response = $this->postJson('/api/legacy-design-review/completed-records/page', [
            'draw' => 1,
            'columns' => [],
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'type' => 'concept_design_review',
            'search' => ['value' => 'Alpha', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recordsTotal', 2)
            ->assertJsonPath('data.recordsFiltered', 1)
            ->assertJsonPath('data.data.0.projectName', 'Alpha Tower')
            ->assertJsonPath('data.data.0.targetModuleLabel', 'Concept Design Review');
    }

    public function test_completed_record_types_returns_counts_by_target_module(): void
    {
        $this->insertSyncRecord([
            'source_stage' => 'design-criteria-report',
            'source_id' => '101',
            'project_name' => 'Alpha Tower',
            'legacy_status_code' => 6,
            'legacy_status_label' => 'Completed',
            'target_module' => 'concept_design_review',
        ]);
        $this->insertSyncRecord([
            'source_stage' => 'submission',
            'source_id' => '303',
            'project_name' => 'Gamma Mall',
            'legacy_status_code' => 1,
            'legacy_status_label' => 'VVE to review',
            'target_module' => 'submission_review',
        ]);

        $response = $this->getJson('/api/legacy-design-review/completed-record-types');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'concept_design_review')
            ->assertJsonPath('data.0.total', 1);
    }

    private function insertSyncRecord(array $overrides): void
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('legacy_design_review_sync_records')->insert(array_merge([
            'source_system' => 'designreview_new',
            'source_database' => 'DB_DesignReview',
            'source_stage' => 'peer-review',
            'source_table' => 'tb_PeerReview',
            'source_id' => '1',
            'project_no' => 'P-001',
            'project_name' => 'Default Project',
            'discipline' => 'Structural',
            'legacy_status_code' => 6,
            'legacy_status_label' => 'Completed',
            'target_module' => 'schematic_design_review',
            'target_table' => 'schematic_design_reviews',
            'target_route' => '/schematic-design-review',
            'sync_status' => 'synced',
            'user_mapping_status' => 'matched',
            'generate_status' => 'pending',
            'generated_id' => null,
            'generated_table' => null,
            'raw_payload' => json_encode([
                'dates' => [
                    'created' => '2026-01-10',
                    'approved' => '2026-01-12',
                ],
                'people' => [
                    'reviewer' => ['name' => 'Reviewer One'],
                    'respondedBy' => ['name' => 'Engineer One'],
                    'teamlead' => ['name' => 'Team Lead'],
                    'director' => ['name' => 'Director One'],
                ],
            ]),
            'mapped_payload' => null,
            'synced_at' => $now,
            'generated_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }
}
