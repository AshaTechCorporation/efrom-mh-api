<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GiftHospitalityOfferingEmployeeNamesTest extends TestCase
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

        Schema::create('gift_hospitality_offerings', function (Blueprint $table): void {
            $table->id();
            $table->string('verified_by')->nullable();
            $table->string('ims_acknowledged_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_2')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->string('create_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('title_name')->nullable();
            $table->softDeletes();
        });
    }

    public function test_show_returns_names_for_all_workflow_assignees_without_replacing_codes(): void
    {
        DB::table('employees')->insert([
            ['code' => 'MTL1476', 'initial' => 'MJR', 'firstname' => 'Sarinlada', 'lastname' => 'Sirimonthonrat', 'title_name' => 'HSE Manager', 'deleted_at' => null],
            ['code' => 'MTL0623', 'initial' => 'MA', 'firstname' => 'Matthew', 'lastname' => 'Silvester', 'title_name' => 'Director', 'deleted_at' => null],
            ['code' => 'MTL1676', 'initial' => 'AC', 'firstname' => 'Acknowledge', 'lastname' => 'One', 'title_name' => 'Lead', 'deleted_at' => null],
            ['code' => 'MTL0094', 'initial' => 'CH', 'firstname' => 'Chen', 'lastname' => 'YaoHui', 'title_name' => 'Director', 'deleted_at' => null],
            ['code' => 'MTL0375', 'initial' => 'SS', 'firstname' => 'Sasiporn', 'lastname' => 'Sirilatthaporn', 'title_name' => 'Accounts', 'deleted_at' => '2026-09-01 00:00:00'],
        ]);
        DB::table('gift_hospitality_offerings')->insert([
            'id' => 26,
            'verified_by' => 'MTL1476',
            'ims_acknowledged_by' => 'MTL1676',
            'approved_by' => 'MTL0094',
            'approved_by_2' => 'UNKNOWN',
            'acknowledged_by' => 'MTL0375',
            'create_by' => 'MTL0623',
        ]);

        $response = $this->getJson('/api/gift_hospitality_offerings/26');
        $response
            ->assertOk()
            ->assertJsonPath('data.verified_by', 'MTL1476')
            ->assertJsonPath('data.verified_by_name', 'MJR, Sarinlada Sirimonthonrat')
            ->assertJsonPath('data.verified_by_title', 'HSE Manager')
            ->assertJsonPath('data.ims_acknowledged_by_name', 'AC, Acknowledge One')
            ->assertJsonPath('data.approved_by_name', 'CH, Chen YaoHui')
            ->assertJsonPath('data.acknowledged_by_name', 'SS, Sasiporn Sirilatthaporn');
        $response->assertJsonPath('data.create_by_name', 'MA, Matthew Silvester');

        $this->assertNull($response->json('data.approved_by_2_name'));
    }
}
