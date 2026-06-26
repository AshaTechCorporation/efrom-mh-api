<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SoftDeleteCreateConflictTest extends TestCase
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

        Schema::create('log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('description');
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('permission_id')->unsigned()->nullable();
            $table->string('code', 50)->nullable()->unique();
            $table->string('username', 50)->unique();
            $table->string('password', 100)->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('Yes');
            $table->integer('zone_market_id')->nullable();
            $table->string('type')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable()->unique();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('register_date')->nullable();
            $table->date('pass_probation_date')->nullable();
            $table->string('sex')->nullable();
            $table->integer('title_id')->nullable();
            $table->string('title_name')->nullable();
            $table->integer('level_id')->nullable();
            $table->string('level_name')->nullable();
            $table->integer('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->integer('employee_type_id')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->integer('work_shift_id')->nullable();
            $table->string('work_shift_name')->nullable();
            $table->integer('head_id')->nullable();
            $table->string('head_name')->nullable();
            $table->string('initial')->nullable();
            $table->boolean('is_approver')->default(false);
            $table->date('next_quota_update')->nullable();
            $table->string('employee_status')->nullable();
            $table->string('active')->nullable();
            $table->date('current_start_period')->nullable();
            $table->date('current_end_period')->nullable();
            $table->timestamp('hrm_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('main_menus', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('main_menu_id')->unsigned();
            $table->string('name');
            $table->integer('parent_id')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('key')->nullable()->unique();
            $table->string('path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('menu_permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('permission_id')->unsigned();
            $table->integer('menu_id')->unsigned();
            $table->tinyInteger('view')->default(0);
            $table->tinyInteger('edit')->default(0);
            $table->tinyInteger('save')->default(0);
            $table->tinyInteger('delete')->default(0);
            $table->tinyInteger('create')->default(0);
            $table->tinyInteger('view_own')->default(0);
            $table->tinyInteger('edit_own')->default(0);
            $table->tinyInteger('delete_own')->default(0);
            $table->tinyInteger('view_all')->default(0);
            $table->tinyInteger('edit_all')->default(0);
            $table->tinyInteger('delete_all')->default(0);
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['permission_id', 'menu_id']);
        });

        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('main_menus')->insert([
            'id' => 1,
            'name' => 'Settings',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_menu_create_restores_soft_deleted_key_without_duplicate_error(): void
    {
        $payload = [
            'name' => ['Signature Settings'],
            'main_menu_id' => [1],
            'parent_id' => [null],
            'sort_order' => [1],
            'key' => ['settings.signature'],
            'path' => ['/settings/signature'],
        ];

        $create = $this->postJson('/api/menu', $payload);

        $create->assertOk()
            ->assertJsonPath('status', true);

        $id = DB::table('menus')->where('key', 'settings.signature')->value('id');

        $delete = $this->deleteJson("/api/menu/{$id}");

        $delete->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertNotNull(DB::table('menus')->where('id', $id)->value('deleted_at'));

        $restore = $this->postJson('/api/menu', array_merge($payload, [
            'name' => ['Signature Settings Restored'],
            'sort_order' => [2],
        ]));

        $restore->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('menus', 1);
        $this->assertSame($id, DB::table('menus')->where('key', 'settings.signature')->value('id'));
        $this->assertSame('Signature Settings Restored', DB::table('menus')->where('id', $id)->value('name'));
        $this->assertNull(DB::table('menus')->where('id', $id)->value('deleted_at'));
    }

    public function test_user_create_restores_soft_deleted_code_without_duplicate_error(): void
    {
        $payload = [
            'permission_id' => 1,
            'code' => 'MTL1520',
            'username' => 'boss',
            'password' => 'secret1',
            'name' => 'Thanawut Jaroenphol',
            'email' => 'boss@example.com',
            'phone' => '0800000000',
        ];

        $create = $this->postJson('/api/user', $payload);

        $create->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.code', 'MTL1520');

        $id = $create->json('data.id');

        $delete = $this->deleteJson("/api/user/{$id}");

        $delete->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertNotNull(DB::table('users')->where('id', $id)->value('deleted_at'));

        $restore = $this->postJson('/api/user', array_merge($payload, [
            'name' => 'Thanawut Restored',
            'email' => 'boss.restored@example.com',
        ]));

        $restore->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.code', 'MTL1520')
            ->assertJsonPath('data.username', 'boss')
            ->assertJsonPath('data.name', 'Thanawut Restored');

        $this->assertDatabaseCount('users', 1);
        $this->assertNull(DB::table('users')->where('id', $id)->value('deleted_at'));
    }

    public function test_employee_sync_restores_soft_deleted_employee_and_user_codes(): void
    {
        config(['services.hrm_employee.url' => 'https://hrm.test/employees']);

        DB::table('employees')->insert([
            'code' => 'MTL9999',
            'username' => 'old.sync',
            'firstname' => 'Old',
            'lastname' => 'Sync',
            'email' => 'old.sync@example.com',
            'active' => 'PER',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        DB::table('users')->insert([
            'permission_id' => 1,
            'code' => 'MTL9999',
            'username' => 'old.sync.del',
            'password' => null,
            'name' => 'Old Sync',
            'email' => 'old.sync@example.com',
            'phone' => null,
            'status' => 'Request',
            'type' => 'sync_ad',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        Http::fake([
            'https://hrm.test/employees*' => Http::response([
                'data' => [[
                    'code' => 'MTL9999',
                    'username' => 'new.sync',
                    'firstname' => 'New',
                    'lastname' => 'Sync',
                    'email' => 'new.sync@example.com',
                    'active' => 'PER',
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/sync/employees');

        $response->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('users_updated', 1);

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(DB::table('employees')->where('code', 'MTL9999')->value('deleted_at'));
        $this->assertNull(DB::table('users')->where('code', 'MTL9999')->value('deleted_at'));
        $syncedUser = DB::table('users')->where('code', 'MTL9999')->first();
        $this->assertSame('new.sync', $syncedUser->username);
        $this->assertSame('New Sync', $syncedUser->name);
        $this->assertSame('new.sync@example.com', $syncedUser->email);
        $this->assertSame(1, (int) $syncedUser->permission_id);
        $this->assertSame('Request', $syncedUser->status);
    }
}
