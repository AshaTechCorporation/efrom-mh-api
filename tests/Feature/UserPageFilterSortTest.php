<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserPageFilterSortTest extends TestCase
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

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('permission_id')->nullable();
            $table->string('username');
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable()->unique();
            $table->string('initial')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('level_name')->nullable();
            $table->string('department_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'Administrative',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            [
                'id' => 2,
                'permission_id' => 1,
                'username' => 'zulu.user',
                'code' => 'MTL0002',
                'name' => 'Zulu User',
                'email' => 'zulu@example.com',
                'status' => 'No',
                'type' => 'sync_ad',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 1,
                'permission_id' => 1,
                'username' => 'alpha.user',
                'code' => 'MTL0001',
                'name' => 'Alpha User',
                'email' => 'alpha@example.com',
                'status' => 'Yes',
                'type' => 'sync_ad',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'permission_id' => 1,
                'username' => 'local.user',
                'code' => 'LOCAL001',
                'name' => 'Local User',
                'email' => 'local@example.com',
                'status' => 'No',
                'type' => 'local',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('employees')->insert([
            [
                'code' => 'MTL0001',
                'initial' => 'AU',
                'employee_type_name' => 'Permanent',
                'title_name' => 'Engineer',
                'level_name' => 'L1',
                'department_name' => 'Engineering',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MTL0002',
                'initial' => 'ZU',
                'employee_type_name' => 'Permanent',
                'title_name' => 'Manager',
                'level_name' => 'L2',
                'department_name' => 'Engineering',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_sync_ad_user_page_filters_by_status(): void
    {
        $response = $this->postJson('/api/user_page', $this->requestPayload([
            'status' => 'No',
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]));

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.username', 'zulu.user');
    }

    public function test_sync_ad_user_page_sorts_by_header_and_row_number(): void
    {
        $byUsername = $this->postJson('/api/user_page', $this->requestPayload([
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]));

        $byUsername->assertOk()
            ->assertJsonPath('data.data.0.username', 'alpha.user')
            ->assertJsonPath('data.data.1.username', 'zulu.user');

        $byId = $this->postJson('/api/user_page', $this->requestPayload([
            'order' => [['column' => 0, 'dir' => 'asc']],
        ]));

        $byId->assertOk()
            ->assertJsonPath('data.data.0.id', 1)
            ->assertJsonPath('data.data.1.id', 2);
    }

    public function test_permission_members_endpoint_returns_assigned_users(): void
    {
        $token = (new LoginController())->genToken(1, (object) ['id' => 1]);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/get_users_by_permission_id/1');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['username' => 'alpha.user'])
            ->assertJsonFragment(['username' => 'zulu.user'])
            ->assertJsonFragment(['username' => 'local.user']);
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'draw' => 1,
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
            'type' => 'sync_ad',
        ], $overrides);
    }
}
