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
            'active' => 'PER',
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
}
