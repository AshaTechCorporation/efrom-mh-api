<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpensesAllowanceListColumnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createExpensesClaimTables();
        $this->createAllowanceTables();
        $this->createEmployeeTable();

        $token = (new LoginController())->genToken(1, (object) [
            'id' => 1,
            'user_id' => 1,
            'username' => 'pagination.tester',
            'employee_code' => 'TESTER',
            'permission_id' => 1,
        ]);
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_expenses_claim_list_sorts_by_the_visible_approved_date_column(): void
    {
        $this->insertExpensesClaim('Creator A', 'Approver A', '2026-08-08 10:00:00', 100);
        $this->insertExpensesClaim('Creator B', 'Approver B', '2026-08-06 10:00:00', 200);

        $response = $this->postJson('/api/expenses_claims_page', [
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.data.0.create_by', 'Creator B')
            ->assertJsonPath('data.data.0.approved_by', 'Approver B')
            ->assertJsonPath('data.data.0.total_baht', 200);
    }

    public function test_allowance_list_sorts_by_the_visible_approver_column(): void
    {
        $this->insertAllowance('Creator A', 'Zulu Approver', '2026-08-06 10:00:00', 100);
        $this->insertAllowance('Creator B', 'Alpha Approver', '2026-08-08 10:00:00', 200);

        $response = $this->postJson('/api/allowance_after_10pm_page', [
            'order' => [['column' => 3, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.data.0.create_by', 'Creator B')
            ->assertJsonPath('data.data.0.di_by', 'Alpha Approver')
            ->assertJsonPath('data.data.0.total_baht', 200);
    }

    public function test_expenses_and_allowance_lists_sort_by_visible_employee_names(): void
    {
        DB::table('employees')->insert([
            [
                'code' => 'Z-CODE',
                'initial' => 'AA',
                'firstname' => 'Alpha',
                'lastname' => 'Employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'A-CODE',
                'initial' => 'ZZ',
                'firstname' => 'Zulu',
                'lastname' => 'Employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->insertExpensesClaim('A-CODE', 'Approver', '2026-08-08 10:00:00', 100);
        $this->insertExpensesClaim('Z-CODE', 'Approver', '2026-08-08 10:00:00', 200);
        $this->insertAllowance('A-CODE', 'Approver', '2026-08-08 10:00:00', 100);
        $this->insertAllowance('Z-CODE', 'Approver', '2026-08-08 10:00:00', 200);

        $request = [
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.data.0.create_by', 'Z-CODE');
        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.data.0.create_by', 'Z-CODE');
    }

    public function test_expenses_and_allowance_status_sort_matches_visible_workflow_status(): void
    {
        $pendingExpense = $this->insertExpensesClaim('Pending Expense', 'Approver', '2026-08-08 10:00:00', 100);
        $approvedExpense = $this->insertExpensesClaim('Approved Expense', 'Approver', '2026-08-08 10:00:00', 200);
        DB::table('expenses_claims')->where('id', $pendingExpense)->update([
            'status' => 'submitted',
            'verified_by_status' => 'pending',
            'approved_by_status' => 'pending',
        ]);
        DB::table('expenses_claims')->where('id', $approvedExpense)->update([
            'status' => 'submitted',
            'verified_by_status' => 'approve',
            'approved_by_status' => 'approve',
        ]);

        $pendingAllowance = $this->insertAllowance('Pending Allowance', 'Approver', '2026-08-08 10:00:00', 100);
        $approvedAllowance = $this->insertAllowance('Approved Allowance', 'Approver', '2026-08-08 10:00:00', 200);
        DB::table('allowance_after_10pm')->where('id', $pendingAllowance)->update([
            'status' => 'submitted',
            'tl_by_status' => 'pending',
            'di_by_status' => 'pending',
        ]);
        DB::table('allowance_after_10pm')->where('id', $approvedAllowance)->update([
            'status' => 'submitted',
            'tl_by_status' => 'approve',
            'di_by_status' => 'approve',
        ]);

        $request = [
            'order' => [['column' => 4, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $approvedExpense);
        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $approvedAllowance);

        $this->assertNotSame($pendingExpense, $approvedExpense);
        $this->assertNotSame($pendingAllowance, $approvedAllowance);
    }

    public function test_expenses_claim_month_filter_runs_before_pagination(): void
    {
        foreach (range(1, 1001) as $index) {
            $this->insertExpensesClaim('August Creator ' . $index, 'Approver', '2026-08-08 10:00:00', 100);
        }
        $this->insertExpensesClaim('July Creator', 'July Approver', '2026-07-08 10:00:00', 700);

        $response = $this->postJson('/api/expenses_claims_page', [
            'approved_month' => '2026-07',
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.create_by', 'July Creator');
    }

    public function test_allowance_month_filter_runs_before_pagination(): void
    {
        foreach (range(1, 1001) as $index) {
            $this->insertAllowance('August Creator ' . $index, 'Approver', '2026-08-08 10:00:00', 100);
        }
        $this->insertAllowance('July Creator', 'July Approver', '2026-07-08 10:00:00', 700);

        $response = $this->postJson('/api/allowance_after_10pm_page', [
            'approved_month' => '2026-07',
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.create_by', 'July Creator');
    }

    public function test_selected_month_can_page_beyond_one_thousand_records(): void
    {
        foreach (range(1, 1001) as $index) {
            $this->insertExpensesClaim('Expense Creator ' . $index, 'Approver', '2026-07-08 10:00:00', 100);
            $this->insertAllowance('Allowance Creator ' . $index, 'Approver', '2026-07-08 10:00:00', 100);
        }

        $request = [
            'approved_month' => '2026-07',
            'tab' => 'all',
            'start' => 1000,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1001)
            ->assertJsonCount(1, 'data.data');

        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1001)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_action_tab_is_filtered_for_the_signed_in_user_and_returns_action_type(): void
    {
        $expenseId = $this->insertExpensesClaim('Creator', 'Approver', '2026-07-08 10:00:00', 100);
        DB::table('expenses_claims')->where('id', $expenseId)->update([
            'verified_by' => 'TESTER',
            'verified_by_status' => 'pending',
            'approved_by_status' => 'pending',
        ]);
        $allowanceId = $this->insertAllowance('Creator', 'Approver', '2026-07-08 10:00:00', 100);
        DB::table('allowance_after_10pm')->where('id', $allowanceId)->update([
            'tl_by' => 'TESTER',
            'tl_by_status' => 'pending',
            'di_by_status' => 'pending',
        ]);

        $request = [
            'tab' => 'action',
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.action_request_count', 1)
            ->assertJsonPath('data.data.0.action_type', 'verified_by_status');

        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.action_request_count', 1)
            ->assertJsonPath('data.data.0.action_type', 'tl_by_status');
    }

    public function test_employee_display_name_search_is_applied_before_pagination(): void
    {
        DB::table('employees')->insert([
            'code' => 'EMP-SEARCH',
            'initial' => 'US',
            'firstname' => 'Unique',
            'lastname' => 'Searchperson',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseId = $this->insertExpensesClaim('EMP-SEARCH', 'Approver', '2026-08-08 10:00:00', 100);
        $allowanceId = $this->insertAllowance('EMP-SEARCH', 'Approver', '2026-08-08 10:00:00', 100);
        $this->insertExpensesClaim('OTHER', 'Approver', '2026-08-08 10:00:00', 200);
        $this->insertAllowance('OTHER', 'Approver', '2026-08-08 10:00:00', 200);

        $request = [
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 1,
            'search' => ['value' => 'Unique Searchperson', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $expenseId);
        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $allowanceId);
    }

    public function test_action_search_sort_and_pagination_share_the_same_query_contract(): void
    {
        $expenseIds = [];
        $allowanceIds = [];
        foreach ([300, 100, 200] as $total) {
            $expenseId = $this->insertExpensesClaim('Action Person', 'Approver', '2026-08-08 10:00:00', $total);
            DB::table('expenses_claims')->where('id', $expenseId)->update([
                'verified_by' => 'TESTER',
                'verified_by_status' => 'pending',
                'approved_by_status' => 'pending',
            ]);
            $expenseIds[$total] = $expenseId;

            $allowanceId = $this->insertAllowance('Action Person', 'Approver', '2026-08-08 10:00:00', $total);
            DB::table('allowance_after_10pm')->where('id', $allowanceId)->update([
                'tl_by' => 'TESTER',
                'tl_by_status' => 'pending',
                'di_by_status' => 'pending',
            ]);
            $allowanceIds[$total] = $allowanceId;
        }

        $request = [
            'tab' => 'action',
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 1,
            'length' => 1,
            'search' => ['value' => 'Action Person', 'regex' => false],
        ];

        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $allowanceIds[200]);

        $request['order'][0]['column'] = 1;
        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $expenseIds[200]);
    }

    public function test_search_status_month_and_my_tab_are_applied_before_pagination(): void
    {
        $expenseId = $this->insertExpensesClaim('Needle Claim', 'Approver', '2026-07-08 10:00:00', 700);
        DB::table('expenses_claims')->where('id', $expenseId)->update([
            'create_by' => 'TESTER',
            'verified_by_status' => 'approve',
            'approved_by_status' => 'approve',
        ]);
        $allowanceId = $this->insertAllowance('Needle Allowance', 'Approver', '2026-07-08 10:00:00', 700);
        DB::table('allowance_after_10pm')->where('id', $allowanceId)->update([
            'create_by' => 'TESTER',
            'tl_by_status' => 'approve',
            'di_by_status' => 'approve',
        ]);
        $this->insertExpensesClaim('Distractor', 'Approver', '2026-08-08 10:00:00', 100);
        $this->insertAllowance('Distractor', 'Approver', '2026-08-08 10:00:00', 100);

        $request = [
            'tab' => 'my',
            'status' => 'approve',
            'approved_month' => '2026-07',
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Needle', 'regex' => false],
        ];

        $this->postJson('/api/expenses_claims_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $expenseId);

        $this->postJson('/api/allowance_after_10pm_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $allowanceId);
    }

    public function test_page_endpoints_require_authentication(): void
    {
        $this->flushHeaders();

        $this->postJson('/api/expenses_claims_page')->assertStatus(401);
        $this->postJson('/api/allowance_after_10pm_page')->assertStatus(401);
    }

    public function test_expenses_claim_draft_is_scoped_to_creator_and_cannot_overwrite_another_users_draft(): void
    {
        $otherDraftId = DB::table('expenses_claims')->insertGetId([
            'voucher_no' => 'EC-OTHER-DRAFT',
            'claimant_name' => 'OTHER-USER',
            'recive_by' => 'OTHER-USER',
            'claim_date' => '2026-09-08',
            'total_baht' => 100,
            'status' => 'draft',
            'create_by' => 'OTHER-USER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ownDraftId = DB::table('expenses_claims')->insertGetId([
            'voucher_no' => 'EC-TESTER-DRAFT',
            'claimant_name' => 'TESTER',
            'recive_by' => 'TESTER',
            'claim_date' => '2026-09-08',
            'total_baht' => 200,
            'status' => 'draft',
            'create_by' => 'TESTER',
            'created_at' => now(),
            'updated_at' => now()->addSecond(),
        ]);

        $this->getJson('/api/expenses_claims_draft')
            ->assertOk()
            ->assertJsonPath('data.id', $ownDraftId)
            ->assertJsonPath('data.create_by', 'TESTER');

        $this->putJson('/api/expenses_claims/' . $otherDraftId, [
            'status' => 'draft',
            'total_baht' => 999,
        ])->assertStatus(403)
            ->assertJsonPath('status', false);

        $this->assertDatabaseHas('expenses_claims', [
            'id' => $otherDraftId,
            'total_baht' => 100,
            'create_by' => 'OTHER-USER',
        ]);

        $submission = [
            'voucher_no' => 'EC-OTHER-DRAFT',
            'claimant_name' => 'TESTER-SUBMISSION',
            'recive_by' => 'TESTER',
            'claim_date' => '2026-09-14',
            'verified_by' => 'VERIFY-01',
            'verified_by_status' => 'pending',
            'approved_by' => 'APPROVE-01',
            'approved_by_status' => 'pending',
            'status' => 'submitted',
            'login_by' => ['employee_code' => 'OTHER-USER'],
            'items' => [[
                'seq' => 1,
                'item_date' => '2026-09-14',
                'project_name' => 'Ownership test',
                'details' => 'Cross-user submission must be rejected',
                'baht' => 999,
            ]],
        ];

        $this->putJson('/api/expenses_claims/' . $otherDraftId, $submission)
            ->assertStatus(403)
            ->assertJsonPath('status', false);

        $this->assertDatabaseHas('expenses_claims', [
            'id' => $otherDraftId,
            'total_baht' => 100,
            'status' => 'draft',
            'create_by' => 'OTHER-USER',
            'update_by' => null,
        ]);

        $submission['voucher_no'] = 'EC-TESTER-DRAFT';
        $submission['login_by'] = ['employee_code' => 'OTHER-USER'];
        $this->putJson('/api/expenses_claims/' . $ownDraftId, $submission)
            ->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('expenses_claims', [
            'id' => $ownDraftId,
            'total_baht' => 999,
            'status' => 'submitted',
            'create_by' => 'TESTER',
            'update_by' => 'TESTER',
        ]);
    }

    public function test_month_filter_rejects_invalid_format(): void
    {
        $this->postJson('/api/expenses_claims_page', [
            'approved_month' => '07/2026',
        ])->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->postJson('/api/allowance_after_10pm_page', [
            'approved_month' => '2026-13',
        ])->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    private function insertExpensesClaim(string $creator, string $approver, string $approvedDate, int $total): int
    {
        return DB::table('expenses_claims')->insertGetId([
            'voucher_no' => uniqid('EC-'),
            'claimant_name' => $creator,
            'recive_by' => $creator,
            'claim_date' => '2026-08-06',
            'total_baht' => $total,
            'status' => 'approved',
            'approved_by' => $approver,
            'approved_by_status' => 'approve',
            'approved_by_date' => $approvedDate,
            'create_by' => $creator,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAllowance(string $creator, string $approver, string $approvedDate, int $total): int
    {
        return DB::table('allowance_after_10pm')->insertGetId([
            'voucher_no' => uniqid('AL-'),
            'claimant_name' => $creator,
            'discipline' => 'MEP',
            'request_date' => '2026-08-06',
            'total_baht' => $total,
            'status' => 'approved',
            'di_by' => $approver,
            'di_by_status' => 'approve',
            'di_by_date' => $approvedDate,
            'create_by' => $creator,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createExpensesClaimTables(): void
    {
        Schema::create('expenses_claims', function (Blueprint $table) {
            $table->increments('id');
            $table->string('voucher_no')->nullable();
            $table->string('claimant_name')->nullable();
            $table->string('recive_by')->nullable();
            $table->date('claim_date')->nullable();
            $table->decimal('total_baht', 15, 2)->nullable();
            $table->text('attachments')->nullable();
            $table->text('draft_payload')->nullable();
            $table->string('status')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('verified_by_status')->nullable();
            $table->dateTime('verified_by_date')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_status')->nullable();
            $table->dateTime('approved_by_date')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses_claim_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('expenses_claim_id');
            $table->unsignedInteger('seq')->nullable();
            $table->date('item_date')->nullable();
            $table->unsignedInteger('project_detail_id')->nullable();
            $table->string('project_code')->nullable();
            $table->string('project_name')->nullable();
            $table->text('details')->nullable();
            $table->decimal('baht', 15, 2)->nullable();
            $table->string('create_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createAllowanceTables(): void
    {
        Schema::create('allowance_after_10pm', function (Blueprint $table) {
            $table->increments('id');
            $table->string('voucher_no')->nullable();
            $table->string('claimant_name')->nullable();
            $table->string('discipline')->nullable();
            $table->date('request_date')->nullable();
            $table->decimal('total_baht', 15, 2)->nullable();
            $table->text('attachments')->nullable();
            $table->string('status')->nullable();
            $table->string('tl_by')->nullable();
            $table->string('tl_by_status')->nullable();
            $table->dateTime('tl_by_date')->nullable();
            $table->string('di_by')->nullable();
            $table->string('di_by_status')->nullable();
            $table->dateTime('di_by_date')->nullable();
            $table->string('account_by')->nullable();
            $table->string('account_by_status')->nullable();
            $table->dateTime('account_by_date')->nullable();
            $table->string('notified_user')->nullable();
            $table->string('notified_user_status')->nullable();
            $table->dateTime('notified_user_date')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('allowance_after_10pm_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('allowance_after_10pm_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_approved_by_and_date_range_combine_with_search_status_and_pagination_for_both_forms(): void
    {
        foreach (['expenses', 'allowance'] as $form) {
            $insert = $form === 'expenses' ? 'insertExpensesClaim' : 'insertAllowance';
            $route = $form === 'expenses' ? '/api/expenses_claims_page' : '/api/allowance_after_10pm_page';
            $this->{$insert}('Unrelated Person', 'APPROVER-A', '2026-09-10 10:00:00', 100);
            $this->{$insert}('Alice Requester', 'APPROVER-B', '2026-09-10 10:00:00', 200);
            $this->{$insert}('Alice Requester', 'APPROVER-A', '2026-08-31 10:00:00', 300);
            $first = $this->{$insert}('Alice Requester', 'APPROVER-A', '2026-09-10 10:00:00', 400);
            $second = $this->{$insert}('Alice Requester', 'APPROVER-A', '2026-09-20 10:00:00', 500);
            DB::table($form === 'expenses' ? 'expenses_claims' : 'allowance_after_10pm')
                ->update([$form === 'expenses' ? 'verified_by_status' : 'tl_by_status' => 'approve']);

            $filters = [
                'approved_by' => 'APPROVER-A',
                'approved_date_from' => '2026-09-01',
                'approved_date_to' => '2026-09-30',
                'status' => 'approve',
                'search' => ['value' => 'Alice', 'regex' => false],
                'start' => 0,
                'length' => 1,
            ];

            $this->postJson($route, $filters)
                ->assertOk()
                ->assertJsonPath('data.total', 2)
                ->assertJsonPath('data.data.0.id', $second);
            $this->postJson($route, array_merge($filters, ['start' => 1]))
                ->assertOk()
                ->assertJsonPath('data.total', 2)
                ->assertJsonPath('data.data.0.id', $first);
        }
    }

    public function test_approved_date_range_rejects_invalid_or_reversed_dates(): void
    {
        foreach (['/api/expenses_claims_page', '/api/allowance_after_10pm_page'] as $route) {
            $this->postJson($route, ['approved_date_from' => '09/01/2026'])->assertStatus(422);
            $this->postJson($route, [
                'approved_date_from' => '2026-09-30',
                'approved_date_to' => '2026-09-01',
            ])->assertStatus(422);
        }
    }

    public function test_approved_expenses_claim_can_be_deleted_with_its_items(): void
    {
        $claimId = $this->insertExpensesClaim('TESTER', 'APPROVER-A', '2026-09-10 10:00:00', 100);
        DB::table('expenses_claim_items')->insert([
            'expenses_claim_id' => $claimId,
            'seq' => 1,
            'item_date' => '2026-09-10',
            'details' => 'Approved document delete regression',
            'baht' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/api/expenses_claims/' . $claimId)
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('expenses_claims', [
            'id' => $claimId,
            'status' => 'approved',
        ]);
        $this->assertNotNull(DB::table('expenses_claims')->where('id', $claimId)->value('deleted_at'));
        $this->assertNotNull(DB::table('expenses_claim_items')->where('expenses_claim_id', $claimId)->value('deleted_at'));
    }

    public function test_approved_allowance_can_be_deleted_with_its_items(): void
    {
        $allowanceId = $this->insertAllowance('TESTER', 'APPROVER-A', '2026-09-10 10:00:00', 100);
        DB::table('allowance_after_10pm_items')->insert([
            'allowance_after_10pm_id' => $allowanceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/api/allowance_after_10pm/' . $allowanceId)
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('allowance_after_10pm', [
            'id' => $allowanceId,
            'status' => 'approved',
        ]);
        $this->assertNotNull(DB::table('allowance_after_10pm')->where('id', $allowanceId)->value('deleted_at'));
        $this->assertNotNull(DB::table('allowance_after_10pm_items')->where('allowance_after_10pm_id', $allowanceId)->value('deleted_at'));
    }

    private function createEmployeeTable(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
