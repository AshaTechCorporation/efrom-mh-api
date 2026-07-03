<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectReviewEmployeeEnrichmentTest extends TestCase
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

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable()->unique();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->string('level_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('concept_design_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->string('form_type')->nullable();
            $table->string('project_id')->nullable();
            $table->string('project_name')->nullable();
            $table->string('project_number')->nullable();
            $table->string('stage')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('discipline')->nullable();
            $table->string('document_location')->nullable();
            $table->string('review_method')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->dateTime('reviewed_by_date')->nullable();
            $table->string('reviewed_by_status')->nullable();
            $table->string('responded_by')->nullable();
            $table->dateTime('responded_by_date')->nullable();
            $table->string('responded_by_status')->nullable();
            $table->string('signed_by_tl')->nullable();
            $table->dateTime('signed_by_tl_date')->nullable();
            $table->string('signed_by_tl_status')->nullable();
            $table->string('signed_by_tl2')->nullable();
            $table->dateTime('signed_by_tl2_date')->nullable();
            $table->string('signed_by_tl2_status')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->string('status')->nullable();
            $table->text('payload')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tender_csa_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->string('form_type')->nullable();
            $table->string('project_id')->nullable();
            $table->string('project_name')->nullable();
            $table->string('project_number')->nullable();
            $table->string('stage')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('discipline')->nullable();
            $table->string('document_location')->nullable();
            $table->string('review_method')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->dateTime('reviewed_by_date')->nullable();
            $table->string('reviewed_by_status')->nullable();
            $table->string('responded_by')->nullable();
            $table->dateTime('responded_by_date')->nullable();
            $table->string('responded_by_status')->nullable();
            $table->string('signed_by_vve')->nullable();
            $table->dateTime('signed_by_vve_date')->nullable();
            $table->string('signed_by_vve_status')->nullable();
            $table->string('signed_by_tl')->nullable();
            $table->dateTime('signed_by_tl_date')->nullable();
            $table->string('signed_by_tl_status')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->string('status')->nullable();
            $table->text('payload')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            [
                'id' => 1,
                'code' => 'MTL0212',
                'initial' => 'JSA',
                'firstname' => 'John Stewart',
                'lastname' => 'Anderson',
                'email' => 'john.anderson@example.com',
                'department_name' => 'Design',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'MTL1752',
                'initial' => 'TJP',
                'firstname' => 'Thanawut',
                'lastname' => 'Jaroenphol',
                'email' => 'thanawut@example.com',
                'department_name' => 'IT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'MTL0909',
                'initial' => 'PKP',
                'firstname' => 'Puckapol',
                'lastname' => 'Ouitrakul',
                'email' => 'puckapol@example.com',
                'department_name' => 'CSA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'MTL0281',
                'initial' => 'VT',
                'firstname' => 'Thanakrit',
                'lastname' => 'Trakulyingyong',
                'email' => 'thanakrit@example.com',
                'department_name' => 'Director',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('concept_design_reviews')->insert([
            'id' => 10,
            'form_type' => 'MTDD-01',
            'project_name' => 'Tower A',
            'project_number' => 'P-001',
            'prepared_by' => 'MTL1752',
            'reviewed_by' => 'MTL0212',
            'responded_by' => 'MTL1752',
            'signed_by_tl' => 'MTL0212',
            'acknowledged_by' => '2',
            'status' => 'in_review',
            'payload' => json_encode([
                'directorForAction' => 'MTL1752',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tender_csa_reviews')->insert([
            'id' => 20,
            'form_type' => 'MTDD-CSA',
            'project_name' => 'Tender CSA',
            'project_number' => 'P-CSA',
            'prepared_by' => 'MTL1752',
            'reviewed_by' => 'MTL0212',
            'responded_by' => 'MTL0909',
            'signed_by_tl' => 'MTL0212',
            'acknowledged_by' => null,
            'status' => 'in_review',
            'payload' => json_encode([
                'acknowledged_by_d_i' => 'MTL0281',
                'directorForAction' => 'MTL0281',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_show_appends_employee_names_without_replacing_codes(): void
    {
        $response = $this->getJson('/api/concept_design_reviews/10');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reviewed_by', 'MTL0212')
            ->assertJsonPath('data.reviewed_by_name', 'JSA, John Stewart Anderson')
            ->assertJsonPath('data.reviewed_by_employee.code', 'MTL0212')
            ->assertJsonPath('data.responded_by', 'MTL1752')
            ->assertJsonPath('data.responded_by_name', 'TJP, Thanawut Jaroenphol')
            ->assertJsonPath('data.acknowledged_by', '2')
            ->assertJsonPath('data.acknowledged_by_name', 'TJP, Thanawut Jaroenphol')
            ->assertJsonPath('data.directorForAction', 'MTL1752')
            ->assertJsonPath('data.directorForActionName', 'TJP, Thanawut Jaroenphol');
    }

    public function test_shared_project_review_page_appends_employee_names(): void
    {
        $response = $this->postJson('/api/project_reviews_page', [
            'type' => 'concept_design_review',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.reviewed_by', 'MTL0212')
            ->assertJsonPath('data.0.reviewed_by_name', 'JSA, John Stewart Anderson')
            ->assertJsonPath('data.0.responded_by', 'MTL1752')
            ->assertJsonPath('data.0.responded_by_name', 'TJP, Thanawut Jaroenphol');
    }

    public function test_tender_csa_review_appends_employee_names_for_action_workflow_fields(): void
    {
        $response = $this->getJson('/api/tender_csa_reviews/20');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.responded_by', 'MTL0909')
            ->assertJsonPath('data.responded_by_name', 'PKP, Puckapol Ouitrakul')
            ->assertJsonPath('data.acknowledged_by_d_i', 'MTL0281')
            ->assertJsonPath('data.acknowledged_by_d_i_name', 'VT, Thanakrit Trakulyingyong')
            ->assertJsonPath('data.directorForAction', 'MTL0281')
            ->assertJsonPath('data.directorForActionName', 'VT, Thanakrit Trakulyingyong');
    }
}
