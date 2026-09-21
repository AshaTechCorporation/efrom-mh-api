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
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createAuthenticationTables();
        $this->createPurchaseRequisitionTable();
        $this->createPurchaseRequisitionItemsTable();
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
            ->assertJsonPath('data.data.0.grand_total', 107)
            ->assertJsonPath('data.data.4.grand_total', 103);
    }

    public function test_pr_page_sorts_requested_by_using_the_visible_employee_name(): void
    {
        DB::table('employees')->insert([
            'code' => 'Z-CODE',
            'initial' => 'AA',
            'firstname' => 'Alpha',
            'lastname' => 'Employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_requisitions')->where('id', 1)->update([
            'requested_by' => 'Z-CODE',
        ]);

        $response = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 5, 'dir' => 'asc']],
            'start' => 0,
            'length' => 20,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', 1)
            ->assertJsonPath('data.data.0.requested_by_label', 'AA, Alpha Employee');
    }

    public function test_pr_page_status_sort_matches_the_visible_workflow_status(): void
    {
        DB::table('purchase_requisitions')->where('id', 1)->update([
            'approved_by' => 'APPROVER',
            'approved_by_status' => 'approve',
        ]);

        $response = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 0,
            'length' => 20,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', 1);
    }

    public function test_pr_page_filters_my_tab_before_pagination_and_counting(): void
    {
        DB::table('purchase_requisitions')->whereIn('id', [1, 2, 3])->update([
            'create_by' => 'OTHER',
            'requested_by' => 'OTHER',
        ]);

        $response = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 0,
            'length' => 5,
            'search' => ['value' => '', 'regex' => false],
            'employee_code' => 'MTLT2607',
            'filters' => ['tab' => 'my', 'status' => ''],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 9)
            ->assertJsonCount(5, 'data.data');
        $this->assertSame(
            ['MTLT2607'],
            collect($response->json('data.data'))->pluck('create_by')->unique()->values()->all()
        );
    }

    public function test_authenticated_draft_create_is_immediately_returned_by_my_requests(): void
    {
        $login = $this->postJson('/api/login', [
            'username' => 'nattapol.srisuk',
            'password' => 'LocalTest-260722!',
        ])->assertOk();

        $token = $login->json('token');
        $subject = 'JWT owner My Requests regression';

        $create = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/purchase_requisitions', [
                'status' => 'draft',
                'to' => 'UAT ONLY',
                'subject' => $subject,
                'date' => '2026-09-14',
                'currency_code' => 'THB',
                'sub_total' => 0,
                'vat_value' => 0,
                'discount' => 0,
                'grand_total' => 0,
                'items' => [],
                'attachments' => [],
            ]);

        $create->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.create_by', 'MTLT2607');

        $createdId = $create->json('data.id');

        $myRequests = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/purchase_requisitions_page', [
                'order' => [['column' => 0, 'dir' => 'desc']],
                'start' => 0,
                'length' => 10,
                'search' => ['value' => $subject, 'regex' => false],
                'filters' => ['tab' => 'my', 'status' => ''],
            ]);

        $myRequests->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $createdId)
            ->assertJsonPath('data.data.0.create_by', 'MTLT2607')
            ->assertJsonPath('data.data.0.subject', $subject);
    }

    public function test_my_requests_includes_legacy_admin_owned_row_by_requester_code(): void
    {
        DB::table('purchase_requisitions')->update([
            'create_by' => 'OTHER',
            'requested_by' => 'OTHER',
        ]);
        DB::table('purchase_requisitions')->where('id', 1)->update([
            'create_by' => 'admin',
            'requested_by' => 'MTLT2607',
            'subject' => 'Legacy owner compatibility',
        ]);

        $login = $this->postJson('/api/login', [
            'username' => 'nattapol.srisuk',
            'password' => 'LocalTest-260722!',
        ])->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer ' . $login->json('token'))
            ->postJson('/api/purchase_requisitions_page', [
                'order' => [['column' => 0, 'dir' => 'desc']],
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'Legacy owner compatibility', 'regex' => false],
                'filters' => ['tab' => 'my', 'status' => ''],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', 1)
            ->assertJsonPath('data.data.0.create_by', 'admin')
            ->assertJsonPath('data.data.0.requested_by', 'MTLT2607');
    }

    public function test_pr_page_filters_pending_tab_for_creator_or_assigned_actor(): void
    {
        DB::table('purchase_requisitions')->update([
            'create_by' => 'OTHER',
            'verified_by' => null,
            'verified_by_status' => null,
        ]);
        DB::table('purchase_requisitions')->where('id', 1)->update([
            'create_by' => 'MTLT2607',
            'verified_by' => 'OTHER',
            'verified_by_status' => 'pending',
        ]);
        DB::table('purchase_requisitions')->where('id', 2)->update([
            'verified_by' => 'MTLT2607',
            'verified_by_status' => 'pending',
        ]);
        DB::table('purchase_requisitions')->where('id', 3)->update([
            'verified_by' => 'MTLT2607',
            'verified_by_status' => 'approved',
        ]);

        $response = $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
            'employee_code' => 'MTLT2607',
            'filters' => ['tab' => 'pending', 'status' => ''],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(2, 'data.data');
        $this->assertSame(
            ['PR-0001', 'PR-0002'],
            collect($response->json('data.data'))->pluck('pr_no')->all()
        );
    }

    public function test_pr_search_finds_the_visible_requested_by_employee_name(): void
    {
        DB::table('employees')->insert([
            'code' => 'EMP-UNIQUE',
            'initial' => 'UQ',
            'firstname' => 'Unique',
            'lastname' => 'Requester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_requisitions')->where('id', 4)->update([
            'requested_by' => 'EMP-UNIQUE',
        ]);

        $this->postJson('/api/purchase_requisitions_page', [
            'order' => [['column' => 5, 'dir' => 'asc']],
            'start' => 0,
            'length' => 1,
            'search' => ['value' => 'Unique Requester', 'regex' => false],
            'filters' => ['tab' => 'all', 'status' => ''],
        ])->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', 4)
            ->assertJsonPath('data.data.0.requested_by_label', 'UQ, Unique Requester');
    }

    public function test_pr_action_tab_applies_search_sort_and_pagination_on_the_server(): void
    {
        DB::table('purchase_requisitions')->update([
            'verified_by_is' => null,
            'verified_by_is_status' => null,
            'verified_by' => null,
            'verified_by_status' => null,
            'approved_by' => null,
            'approved_by_status' => null,
            'approved_by_2' => null,
            'approved_by_2_status' => null,
            'acknowledged_by' => null,
            'acknowledged_by_status' => null,
            'action_by_admin' => null,
            'action_by_admin_status' => null,
            'action_by_admin_date' => null,
        ]);
        DB::table('purchase_requisitions')->where('id', 1)->update([
            'subject' => 'Target Charlie',
            'verified_by' => 'MTLT2607',
            'verified_by_status' => 'pending',
        ]);
        DB::table('purchase_requisitions')->where('id', 2)->update([
            'subject' => 'Target Alpha',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'approve',
            'approved_by' => 'MTLT2607',
            'approved_by_status' => 'pending',
        ]);
        DB::table('purchase_requisitions')->where('id', 3)->update([
            'subject' => 'Target Hidden',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'pending',
            'approved_by' => 'MTLT2607',
            'approved_by_status' => 'pending',
        ]);
        DB::table('purchase_requisitions')->where('id', 4)->update([
            'subject' => 'Other Admin Not Reached',
            'action_by_admin' => 'MTLT2607',
            'action_by_admin_status' => 'pending',
        ]);

        $request = [
            'employee_code' => 'MTLT2607',
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 0,
            'length' => 1,
            'search' => ['value' => 'Target', 'regex' => false],
            'filters' => ['tab' => 'action', 'status' => ''],
        ];

        $this->postJson('/api/purchase_requisitions_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', 2);

        $request['start'] = 1;
        $this->postJson('/api/purchase_requisitions_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.id', 1);

        $request['start'] = 0;
        $request['length'] = 10;
        $request['search']['value'] = '';
        $request['order'][0]['column'] = 6;
        $this->postJson('/api/purchase_requisitions_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.id', 2);
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

    private function createPurchaseRequisitionItemsTable(): void
    {
        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_requisition_id');
            $table->string('item');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('need_asset_code_registration')->default(false);
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_requisition_id')
                ->references('id')
                ->on('purchase_requisitions')
                ->onDelete('cascade');
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
