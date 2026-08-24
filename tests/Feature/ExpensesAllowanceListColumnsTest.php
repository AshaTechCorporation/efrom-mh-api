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
}
