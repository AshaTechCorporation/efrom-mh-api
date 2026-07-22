<?php

namespace Tests\Feature;

use App\Http\Controllers\FeeSheetController;
use App\Models\FeeSheet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ServerSideListSortingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createConceptReviewTables();
        $this->createSubConsultantAssessmentTables();
        $this->createFeeSheetSortTables();
    }

    public function test_design_review_sort_is_applied_before_page_slice(): void
    {
        foreach (['Zulu', 'Echo', 'Bravo', 'Delta', 'Alpha', 'Charlie'] as $index => $name) {
            DB::table('concept_design_reviews')->insert([
                'project_number' => sprintf('P-%02d', $index + 1),
                'project_name' => $name,
                'discipline' => 'MEP',
                'status' => 'submitted',
                'payload' => '{}',
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->postJson('/api/concept_design_reviews_page', [
            'draw' => 1,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 2,
            'length' => 2,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 6)
            ->assertJsonPath('data.0.project_name', 'Charlie')
            ->assertJsonPath('data.1.project_name', 'Delta')
            ->assertJsonPath('data.0.No', 3)
            ->assertJsonPath('data.1.No', 4);
    }

    public function test_sub_consultant_unbounded_request_returns_every_sorted_row(): void
    {
        foreach (['Siam Works', 'Ananda Design', 'Metro Systems', 'Bright Build'] as $index => $company) {
            DB::table('sub_consultant_assessments')->insert([
                'company' => $company,
                'item1_total_score' => 20 + $index,
                'status' => 'submitted',
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->postJson('/api/sub_consultant_assessments_page', [
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => -1,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total', 4)
            ->assertJsonCount(4, 'data.data')
            ->assertJsonPath('data.data.0.company', 'Ananda Design')
            ->assertJsonPath('data.data.3.company', 'Siam Works');
    }

    public function test_fee_sheet_sort_uses_displayed_proposal_number_and_net_fee(): void
    {
        $proposalNumbers = ['PR-40', 'PR-10', 'PR-60', 'PR-20', 'PR-50', 'PR-30'];
        $netFees = [400, 100, 600, 200, 500, 300];

        foreach ($proposalNumbers as $index => $proposalNumber) {
            $id = $index + 1;
            DB::table('proposal_project_references')->insert([
                'id' => $id,
                'proposal_number' => $proposalNumber,
            ]);
            DB::table('fee_sheet_revisions')->insert([
                'id' => $id,
                'fee_sheet_id' => $id,
                'project_name' => "Local Project {$id}",
                'mt_project_no' => "MT-{$id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('fee_sheets')->insert([
                'id' => $id,
                'proposal_project_reference_id' => $id,
                'current_revision_id' => $id,
                'mt_project_no' => "MT-{$id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('fee_agreements')->insert([
                'id' => $id,
                'revision_id' => $id,
                'net_fee_excl_vat' => $netFees[$index],
            ]);
        }

        $proposalQuery = FeeSheet::query();
        $this->applyFeeSheetOrdering($proposalQuery, 'proposal_number', 'asc');
        $this->assertSame([2, 4, 6, 1, 5, 3], $proposalQuery->pluck('id')->all());

        $netFeeQuery = FeeSheet::query();
        $this->applyFeeSheetOrdering($netFeeQuery, 'nett_fee', 'desc');
        $this->assertSame([3, 5, 1, 6, 4, 2], $netFeeQuery->pluck('id')->all());
    }

    private function applyFeeSheetOrdering($query, string $column, string $direction): void
    {
        $method = new ReflectionMethod(FeeSheetController::class, 'applyIndexOrdering');
        $method->setAccessible(true);
        $method->invoke(app(FeeSheetController::class), $query, $column, $direction);
    }

    private function createConceptReviewTables(): void
    {
        Schema::create('concept_design_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->string('project_number')->nullable();
            $table->string('project_name')->nullable();
            $table->string('discipline')->nullable();
            $table->string('responded_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('signed_by_tl2')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->string('status')->nullable();
            $table->text('payload')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createSubConsultantAssessmentTables(): void
    {
        Schema::create('sub_consultant_assessments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('form_code')->nullable();
            $table->string('to')->nullable();
            $table->string('circ')->nullable();
            $table->string('company')->nullable();
            $table->integer('item1_total_score')->nullable();
            $table->string('recommendation')->nullable();
            $table->string('status')->nullable();
            $table->string('assessed_by')->nullable();
            $table->dateTime('assessed_by_date')->nullable();
            $table->string('assessed_by_status')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_by_date')->nullable();
            $table->string('approved_by_status')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sub_consultant_assessment_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('assessment_id');
            $table->string('file_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createFeeSheetSortTables(): void
    {
        Schema::create('fee_sheets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('proposal_project_reference_id')->nullable();
            $table->unsignedInteger('current_revision_id')->nullable();
            $table->string('mt_project_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('fee_sheet_revisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fee_sheet_id');
            $table->string('project_name')->nullable();
            $table->string('mt_project_no')->nullable();
            $table->string('client_name')->nullable();
            $table->string('status')->nullable();
            $table->string('form_filled_by_id')->nullable();
            $table->unsignedInteger('project_type_id')->nullable();
            $table->unsignedInteger('discipline_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('proposal_project_references', function (Blueprint $table) {
            $table->increments('id');
            $table->string('proposal_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('fee_agreements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('revision_id');
            $table->decimal('net_fee_excl_vat', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
