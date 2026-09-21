<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Iis51ExpensesClaimVoucherAllocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createExpensesClaimTables();
    }

    public function test_two_new_drafts_with_the_same_preview_receive_vouchers_in_save_order(): void
    {
        $previewA = $this->asUser('USER-A')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->json('data.voucher_no');
        $previewB = $this->asUser('USER-B')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->json('data.voucher_no');

        $this->assertSame($this->expectedVoucher(1), $previewA);
        $this->assertSame($previewA, $previewB);

        $first = $this->asUser('USER-A')->postJson('/api/expenses_claims', [
            'voucher_no' => $previewA,
            'status' => 'draft',
            'total_baht' => 10,
        ])->assertOk()->assertJsonPath('status', true);

        $second = $this->asUser('USER-B')->postJson('/api/expenses_claims', [
            'voucher_no' => $previewB,
            'status' => 'draft',
            'total_baht' => 20,
        ])->assertOk()->assertJsonPath('status', true);

        $first->assertJsonPath('data.voucher_no', $this->expectedVoucher(1));
        $second->assertJsonPath('data.voucher_no', $this->expectedVoucher(2));
        $this->assertSame(2, DB::table('expenses_claims')->count());
        $this->assertSame(2, DB::table('expenses_claims')->distinct()->count('voucher_no'));
    }

    public function test_two_new_submissions_with_the_same_preview_receive_vouchers_in_save_order(): void
    {
        $previewA = $this->asUser('USER-A')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->json('data.voucher_no');
        $previewB = $this->asUser('USER-B')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->json('data.voucher_no');

        $first = $this->asUser('USER-A')->postJson(
            '/api/expenses_claims',
            $this->submissionPayload($previewA, 'USER-A')
        )->assertOk()->assertJsonPath('status', true);

        $second = $this->asUser('USER-B')->postJson(
            '/api/expenses_claims',
            $this->submissionPayload($previewB, 'USER-B')
        )->assertOk()->assertJsonPath('status', true);

        $first->assertJsonPath('data.voucher_no', $this->expectedVoucher(1));
        $second->assertJsonPath('data.voucher_no', $this->expectedVoucher(2));
        $this->assertSame(2, DB::table('expenses_claims')->count());
        $this->assertSame(2, DB::table('expenses_claims')->distinct()->count('voucher_no'));
    }

    public function test_saved_draft_keeps_its_voucher_when_reopened_and_updated(): void
    {
        $preview = $this->asUser('USER-A')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->json('data.voucher_no');

        $created = $this->asUser('USER-A')->postJson('/api/expenses_claims', [
            'voucher_no' => $preview,
            'status' => 'draft',
            'total_baht' => 10,
        ])->assertOk()->assertJsonPath('status', true);

        $draftId = $created->json('data.id');
        $voucher = $created->json('data.voucher_no');

        $this->asUser('USER-A')->getJson('/api/expenses_claims_draft')
            ->assertOk()
            ->assertJsonPath('data.id', $draftId)
            ->assertJsonPath('data.voucher_no', $voucher);

        $this->asUser('USER-A')->putJson('/api/expenses_claims/' . $draftId, [
            'voucher_no' => $voucher,
            'status' => 'draft',
            'total_baht' => 99,
        ])->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $draftId)
            ->assertJsonPath('data.voucher_no', $voucher);

        $this->assertDatabaseHas('expenses_claims', [
            'id' => $draftId,
            'voucher_no' => $voucher,
            'total_baht' => 99,
            'create_by' => 'USER-A',
            'update_by' => 'USER-A',
            'status' => 'draft',
        ]);
    }

    public function test_new_save_uses_the_next_highest_sequence_instead_of_reusing_a_gap(): void
    {
        DB::table('expenses_claims')->insert([
            'voucher_no' => $this->expectedVoucher(5),
            'claimant_name' => 'LEGACY-USER',
            'status' => 'submitted',
            'create_by' => 'LEGACY-USER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preview = $this->asUser('USER-A')->getJson('/api/expenses_claims/create')
            ->assertOk()
            ->assertJsonPath('data.voucher_no', $this->expectedVoucher(6))
            ->json('data.voucher_no');

        $this->asUser('USER-A')->postJson('/api/expenses_claims', [
            'voucher_no' => $preview,
            'status' => 'draft',
            'total_baht' => 10,
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.voucher_no', $this->expectedVoucher(6));
    }

    private function submissionPayload(string $voucher, string $actor): array
    {
        return [
            'voucher_no' => $voucher,
            'claimant_name' => $actor,
            'recive_by' => $actor,
            'claim_date' => '2026-09-21',
            'verified_by' => 'VERIFY-01',
            'approved_by' => 'APPROVE-01',
            'status' => 'submitted',
            'items' => [[
                'seq' => 1,
                'item_date' => '2026-09-21',
                'project_name' => 'IIS-51 verification',
                'details' => 'Voucher allocation order',
                'baht' => 100,
            ]],
        ];
    }

    private function expectedVoucher(int $sequence): string
    {
        return 'EC-' . now()->format('Ymd') . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function asUser(string $employeeCode): self
    {
        $token = (new LoginController())->genToken(1, (object) [
            'id' => $employeeCode,
            'user_id' => $employeeCode,
            'username' => strtolower($employeeCode),
            'employee_code' => $employeeCode,
            'permission_id' => 1,
        ]);

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function createExpensesClaimTables(): void
    {
        Schema::create('expenses_claims', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('voucher_no')->unique();
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

        Schema::create('expenses_claim_items', function (Blueprint $table): void {
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
}
