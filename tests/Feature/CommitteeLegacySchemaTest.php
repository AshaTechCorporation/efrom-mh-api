<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommitteeLegacySchemaTest extends TestCase
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

        Schema::create('committees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable()->unique();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->string('department_name')->nullable();
            $table->string('active')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('committee_employees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('committee_id');
            $table->string('employee_code');
            $table->timestamps();
            $table->unique(['committee_id', 'employee_code']);
        });

        DB::table('committees')->insert([
            'id' => 1,
            'name' => 'ISO Committee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'code' => 'MTL1520',
            'initial' => 'TWT',
            'firstname' => 'Thanawut',
            'lastname' => 'Jaroenphol',
            'email' => 'boss@example.com',
            'department_name' => 'IT',
            'active' => 'PER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('committee_employees')->insert([
            'committee_id' => 1,
            'employee_code' => 'MTL1520',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_committee_page_and_show_support_legacy_pivot_without_deleted_at(): void
    {
        $page = $this->postJson('/api/committees_page', [
            'draw' => 1,
            'columns' => [],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $page->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.data.0.name', 'ISO Committee')
            ->assertJsonPath('data.data.0.employees.0.code', 'MTL1520')
            ->assertJsonPath('data.data.0.employees.0.initial', 'TWT');

        $show = $this->getJson('/api/committees/1');

        $show->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.employees.0.code', 'MTL1520')
            ->assertJsonPath('data.employees.0.initial', 'TWT');
    }
}
