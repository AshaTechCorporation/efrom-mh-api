<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseRequisitionRuntimeTest extends TestCase
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

        $this->createAuthenticationTables();
        $this->createPurchaseRequisitionTable();
        $this->seedHumanTestUser();
        $this->seedPurchaseRequisitions();
    }

    public function test_human_test_user_can_log_in_and_validate_token(): void
    {
        $login = $this->postJson('/api/login', [
            'username' => 'nattapol.srisuk',
            'password' => 'LocalTest-260722!',
        ]);

        $login->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Nattapol Srisuk')
            ->assertJsonPath('data.department', 'Procurement');
        $this->assertArrayNotHasKey('password', $login->json('data'));

        $token = $login->json('token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/check_login')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('log', [
            'user_id' => 'nattapol.srisuk',
            'type' => 'Login',
        ]);
    }

    public function test_pr_page_applies_pagination_row_number_and_employee_label(): void
    {
        $response = $this->postJson('/api/purchase_requisitions_page', [
            'draw' => 1,
            'columns' => [['data' => 'pr_no']],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 5,
            'length' => 5,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 12)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.data.0.pr_no', 'PR-0007')
            ->assertJsonPath('data.data.0.No', 6)
            ->assertJsonPath('data.data.0.requested_by_label', 'NS, Nattapol Srisuk');
    }

    public function test_pr_page_sorts_the_full_result_before_slicing_pages(): void
    {
        for ($id = 1; $id <= 12; $id++) {
            DB::table('purchase_requisitions')
                ->where('id', $id)
                ->update(['subject' => sprintf('Subject %02d', 13 - $id)]);
        }

        $firstPage = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 0,
            'length' => 5,
            'search' => ['value' => '', 'regex' => false],
        ]);
        $secondPage = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 5,
            'length' => 5,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $firstPage->assertOk()
            ->assertJsonPath('data.data.0.subject', 'Subject 01')
            ->assertJsonPath('data.data.4.subject', 'Subject 05');
        $secondPage->assertOk()
            ->assertJsonPath('data.data.0.subject', 'Subject 06')
            ->assertJsonPath('data.data.4.subject', 'Subject 10');

        $numericPage = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 6, 'dir' => 'desc']],
            'start' => 5,
            'length' => 5,
            'search' => ['value' => '', 'regex' => false],
        ]);
        $numericPage->assertOk()
            ->assertJsonPath('data.data.0.grand_total', '107')
            ->assertJsonPath('data.data.4.grand_total', '103');
    }

    private function createAuthenticationTables(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('permission_id')->nullable();
            $table->string('code')->unique();
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('Yes');
            $table->integer('zone_market_id')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('department_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('description');
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createPurchaseRequisitionTable(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->increments('id');
            foreach ([
                'status', 'pr_no', 'to', 'subject', 'date', 'deadline',
                'attachments', 'recommended_by', 'currency_code', 'received_from',
                'reasons_for_purchase', 'other_conditions', 'payment_term',
                'requested_by', 'requested_by_status', 'requested_date',
                'verified_by_is', 'approved_by', 'approved_by_status', 'approved_date',
                'verified_is_date', 'verified_by_is_status', 'verified_by',
                'verified_by_status', 'verified_date', 'approved_by_2',
                'approved_by_2_status', 'approved_by_2_date', 'acknowledged_by',
                'acknowledged_by_status', 'acknowledged_date', 'action_by_admin',
                'action_by_admin_status', 'action_by_admin_date', 'create_by', 'update_by',
            ] as $column) {
                $table->text($column)->nullable();
            }
            foreach ([
                'vat', 'quotation_attached', 'need_asset_code_registration',
            ] as $column) {
                $table->boolean($column)->nullable();
            }
            foreach (['sub_total', 'vat_value', 'discount', 'grand_total'] as $column) {
                $table->decimal($column, 15, 2)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function seedHumanTestUser(): void
    {
        $now = now();
        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'Administrative',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->insert([
            'id' => 1,
            'permission_id' => 1,
            'code' => 'MTLT2607',
            'username' => 'nattapol.srisuk',
            'password' => md5('LocalTest-260722!'),
            'name' => 'Nattapol Srisuk',
            'email' => 'nattapol.srisuk@example.test',
            'phone' => '0800002607',
            'status' => 'Yes',
            'create_by' => 'qa.local',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('employees')->insert([
            'id' => 1,
            'code' => 'MTLT2607',
            'username' => 'nattapol.srisuk',
            'email' => 'nattapol.srisuk@example.test',
            'initial' => 'NS',
            'firstname' => 'Nattapol',
            'lastname' => 'Srisuk',
            'department_name' => 'Procurement',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedPurchaseRequisitions(): void
    {
        $rows = [];
        for ($number = 1; $number <= 12; $number++) {
            $rows[] = [
                'status' => 'submitted',
                'pr_no' => sprintf('PR-%04d', $number),
                'to' => 'Procurement Team',
                'subject' => 'Office supplies ' . $number,
                'date' => '2026-07-22',
                'reasons_for_purchase' => 'Routine operational supplies',
                'requested_by' => 'MTLT2607',
                'requested_by_status' => 'pending',
                'currency_code' => 'THB',
                'sub_total' => 100 + $number,
                'vat_value' => 0,
                'discount' => 0,
                'grand_total' => 100 + $number,
                'create_by' => 'MTLT2607',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('purchase_requisitions')->insert($rows);
    }
}
