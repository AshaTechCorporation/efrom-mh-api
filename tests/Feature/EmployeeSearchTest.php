<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
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
            $table->string('code')->nullable();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->string('level_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->boolean('is_approver')->default(false);
            $table->string('active')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            'code' => 'MTL1503',
            'initial' => 'CRC',
            'firstname' => 'Chariya',
            'lastname' => 'Chaiwirakul',
            'email' => 'chariya@example.com',
            'department_name' => 'Finance',
            'active' => 'RES',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_search_finds_employee_by_initial(): void
    {
        $response = $this->getJson('/api/employees?search=CRC&limit=20');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'MTL1503')
            ->assertJsonPath('data.0.initial', 'CRC');
    }

    public function test_workflow_role_filter_is_applied_before_search_and_limit(): void
    {
        foreach (range(1, 25) as $index) {
            DB::table('employees')->insert([
                'code' => sprintf('EE%02d', $index),
                'initial' => sprintf('EE%02d', $index),
                'firstname' => 'Matching',
                'lastname' => sprintf('Employee %02d', $index),
                'level_name' => 'EE',
                'title_name' => 'Executive Engineer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('employees')->insert([
            [
                'code' => 'DI01',
                'initial' => 'DI',
                'firstname' => 'Matching',
                'lastname' => 'Director',
                'level_name' => 'DI',
                'title_name' => 'Director',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MD01',
                'initial' => 'MD',
                'firstname' => 'Matching',
                'lastname' => 'Managing Director',
                'level_name' => 'MD',
                'title_name' => 'Managing Director',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/employees?workflow_role=di_md&search=Matching&limit=200')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'DI01')
            ->assertJsonPath('data.1.code', 'MD01');
    }

    public function test_exact_initial_filter_returns_only_the_requested_initial(): void
    {
        DB::table('employees')->insert([
            [
                'code' => 'JN01',
                'initial' => 'JN',
                'firstname' => 'Accounts',
                'lastname' => 'Acknowledger',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'JNB01',
                'initial' => 'JNB',
                'firstname' => 'Other',
                'lastname' => 'Employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/employees?initial_exact=jn&limit=200')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'JN01');
    }
}
