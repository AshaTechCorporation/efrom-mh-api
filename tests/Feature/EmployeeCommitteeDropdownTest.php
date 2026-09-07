<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeCommitteeDropdownTest extends TestCase
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

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['code', 'initial', 'firstname', 'lastname', 'email', 'level_name', 'title_name', 'department_name', 'employee_type_name', 'active'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('is_approver')->default(0);
            $table->softDeletes();
        });
        Schema::create('committees', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('committee_employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('committee_id');
            $table->string('employee_code');
            $table->softDeletes();
        });
    }

    public function test_committee_dropdown_searches_before_limiting_and_caps_results_at_twenty(): void
    {
        $committeeId = DB::table('committees')->insertGetId(['name' => 'IMS/ADM']);
        for ($i = 1; $i <= 25; $i++) {
            $code = sprintf('IMS%02d', $i);
            DB::table('employees')->insert([
                'code' => $code,
                'firstname' => $i === 25 ? 'UniqueTarget' : sprintf('Member%02d', $i),
                'lastname' => 'IMS',
            ]);
            DB::table('committee_employees')->insert(['committee_id' => $committeeId, 'employee_code' => $code]);
        }
        DB::table('employees')->insert(['code' => 'OUTSIDE', 'firstname' => 'UniqueTarget', 'lastname' => 'Other']);

        $this->getJson('/api/employees?committee_name=IMS%2FADM&limit=200')
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $this->getJson('/api/employees?committee_name=IMS%2FADM&search=UniqueTarget&limit=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'IMS25');
    }
}
