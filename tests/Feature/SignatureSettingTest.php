<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SignatureSettingTest extends TestCase
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
            $table->string('initial', 50)->nullable();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('level_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->string('active', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('signature_settings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->tinyInteger('is_active')->default(1);
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            $this->employeeRow('MTL1520', 'TW', 'Thanawut', 'Jaroenphol'),
            $this->employeeRow('MTL2000', 'AB', 'Active', 'Boss'),
            $this->employeeRow('MTL3000', 'IN', 'Inactive', 'Person'),
        ]);
    }

    public function test_create_update_and_page_signature_setting(): void
    {
        $create = $this->postJson('/api/signature_settings', [
            'employee_code' => 'MTL1520',
            'is_active' => 1,
        ]);

        $create->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.employee_code', 'MTL1520')
            ->assertJsonPath('data.is_active', 1);

        $id = $create->json('data.id');

        $update = $this->putJson("/api/signature_settings/{$id}", [
            'employee_code' => 'MTL1520',
            'is_active' => 0,
        ]);

        $update->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.is_active', 0);

        $page = $this->postJson('/api/signature_settings_page', [
            'draw' => 1,
            'columns' => [],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Thanawut', 'regex' => false],
        ]);

        $page->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.data.0.employee_code', 'MTL1520')
            ->assertJsonPath('data.data.0.firstname', 'Thanawut');
    }

    public function test_duplicate_employee_code_is_rejected(): void
    {
        DB::table('signature_settings')->insert([
            'employee_code' => 'MTL1520',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/signature_settings', [
            'employee_code' => 'MTL1520',
            'is_active' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('employee_code');
    }

    public function test_active_lookup_excludes_inactive_rows(): void
    {
        DB::table('signature_settings')->insert([
            [
                'employee_code' => 'MTL2000',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_code' => 'MTL3000',
                'is_active' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/get_signature_settings?active=1&codes[]=MTL2000&codes[]=MTL3000');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_code', 'MTL2000');
    }

    public function test_lookup_with_multiple_codes_returns_only_matching_active_codes(): void
    {
        DB::table('signature_settings')->insert([
            [
                'employee_code' => 'MTL1520',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_code' => 'MTL2000',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/get_signature_settings?active=1&codes[]=MTL1520&codes[]=UNKNOWN');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_code', 'MTL1520');
    }

    private function employeeRow(string $code, string $initial, string $firstname, string $lastname): array
    {
        return [
            'code' => $code,
            'initial' => $initial,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => strtolower($code) . '@example.com',
            'level_name' => null,
            'title_name' => 'Developer',
            'department_name' => 'IT',
            'employee_type_name' => 'Permanent',
            'active' => 'PER',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
