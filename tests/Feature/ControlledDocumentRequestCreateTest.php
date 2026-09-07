<?php

namespace Tests\Feature;

use App\Http\Controllers\ControlledDocumentRequestsController;
use App\Http\Controllers\LoginController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControlledDocumentRequestCreateTest extends TestCase
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

        Schema::dropIfExists('controlled_document_requests');
        Schema::dropIfExists('controlled_document_request_number_sequences');
        Schema::dropIfExists('log');

        Schema::create('log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('description');
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('controlled_document_request_number_sequences', function (Blueprint $table) {
            $table->string('document_key')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('controlled_document_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('to')->nullable();
            $table->string('from')->nullable();
            $table->date('date')->nullable();
            $table->string('cdr_no')->nullable();
            $table->string('categories')->nullable();
            $table->string('request_for')->nullable();
            $table->string('document_name')->nullable();
            $table->string('current_revision')->nullable();
            $table->text('reason_description')->nullable();
            $table->date('effective_date_purpose')->nullable();
            $table->text('attach_document_note')->nullable();
            $table->text('attachments')->nullable();
            $table->string('requested_by')->nullable();
            $table->dateTime('requested_date')->nullable();
            $table->text('review_comments')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_by_status')->nullable();
            $table->dateTime('reviewed_by_date')->nullable();
            $table->text('approval_comments')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_status')->nullable();
            $table->dateTime('approved_by_date')->nullable();
            $table->string('new_revision')->nullable();
            $table->date('action_effective_date')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->string('acknowledged_by_status')->nullable();
            $table->string('acknowledged_by_status_2')->nullable();
            $table->dateTime('acknowledged_by_date')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_create_accepts_long_reason_and_locks_requester_to_actor(): void
    {
        $longReason = str_repeat('Detailed controlled-document reason. ', 20);
        $response = $this->controller()->store($this->request([
            'reason_description' => $longReason,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['status']);
        $this->assertSame('003/2026', $payload['data']['cdr_no']);
        $this->assertSame('CRC', $payload['data']['requested_by']);
        $this->assertSame($longReason, DB::table('controlled_document_requests')->value('reason_description'));
        $this->assertSame('CRC', DB::table('controlled_document_requests')->value('create_by'));
        $this->assertNull(DB::table('controlled_document_requests')->value('acknowledged_by_status'));
        $this->assertNull(DB::table('controlled_document_requests')->value('reviewed_by_date'));
        $this->assertNull(DB::table('controlled_document_requests')->value('approved_by_date'));
        $this->assertNull(DB::table('controlled_document_requests')->value('acknowledged_by_date'));
        $this->assertSame('pending', DB::table('controlled_document_requests')->value('reviewed_by_status'));
        $this->assertSame('pending', DB::table('controlled_document_requests')->value('approved_by_status'));
        $this->assertSame('pending', DB::table('controlled_document_requests')->value('acknowledged_by_status_2'));
    }

    public function test_create_rejects_multiple_categories_missing_attachment_and_early_effective_date(): void
    {
        $response = $this->controller()->store($this->request([
            'categories' => 'ims,acp',
            'attachments' => [],
            'effective_date_purpose' => '2026-07-23',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $errors = $response->getData(true)['errors'];
        $this->assertArrayHasKey('categories', $errors);
        $this->assertArrayHasKey('attachments', $errors);
        $this->assertArrayHasKey('effective_date_purpose', $errors);
        $this->assertSame(0, DB::table('controlled_document_requests')->count());
    }

    public function test_update_preserves_original_requester(): void
    {
        $createResponse = $this->controller()->store($this->request());
        $id = $createResponse->getData(true)['data']['id'];

        $updateRequest = Request::create('/api/controlled_document_requests/' . $id, 'PUT', [
            'requested_by' => 'SPOOFED-UPDATE',
            'reason_description' => 'Updated reason',
            'reviewed_by_status' => 'approved',
        ]);
        $updateRequest->merge([
            'login_by' => (object) ['employee_code' => 'EDITOR'],
        ]);

        $response = $this->controller()->update($updateRequest, $id);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('CRC', $response->getData(true)['data']['requested_by']);
        $record = DB::table('controlled_document_requests')->find($id);
        $this->assertSame('CRC', $record->requested_by);
        $this->assertSame('EDITOR', $record->update_by);
        $this->assertSame('Updated reason', $record->reason_description);
    }

    public function test_reassigning_approver_resets_workflow_status_and_signature_date(): void
    {
        $createResponse = $this->controller()->store($this->request());
        $id = $createResponse->getData(true)['data']['id'];
        DB::table('controlled_document_requests')->where('id', $id)->update([
            'approved_by_status' => 'approved',
            'approved_by_date' => '2026-07-23 10:00:00',
        ]);

        $updateRequest = Request::create('/api/controlled_document_requests/' . $id, 'PUT', [
            'approved_by' => 'APP002',
            'approved_by_status' => 'approved',
            'approved_by_date' => '2026-07-23 10:00:00',
        ]);
        $updateRequest->merge(['login_by' => (object) ['employee_code' => 'EDITOR']]);

        $response = $this->controller()->update($updateRequest, $id);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('controlled_document_requests', [
            'id' => $id,
            'approved_by' => 'APP002',
            'approved_by_status' => 'pending',
            'approved_by_date' => null,
        ]);
    }

    public function test_legacy_step_one_status_is_preserved_but_cannot_be_changed(): void
    {
        $createResponse = $this->controller()->store($this->request());
        $id = $createResponse->getData(true)['data']['id'];
        DB::table('controlled_document_requests')->where('id', $id)->update([
            'acknowledged_by_status' => 'pending',
        ]);

        $changeRequest = Request::create('/api/controlled_document_requests/' . $id, 'PUT', [
            'acknowledged_by_status' => 'approve',
        ]);
        $changeRequest->merge(['login_by' => (object) ['employee_code' => 'EDITOR']]);

        $response = $this->controller()->update($changeRequest, $id);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'pending',
            DB::table('controlled_document_requests')->where('id', $id)->value('acknowledged_by_status')
        );

        $normalUpdate = Request::create('/api/controlled_document_requests/' . $id, 'PUT', [
            'reason_description' => 'Legacy history preserved',
        ]);
        $normalUpdate->merge(['login_by' => (object) ['employee_code' => 'EDITOR']]);
        $normalResponse = $this->controller()->update($normalUpdate, $id);

        $this->assertSame(201, $normalResponse->getStatusCode());
        $this->assertSame(
            'pending',
            DB::table('controlled_document_requests')->where('id', $id)->value('acknowledged_by_status')
        );
    }

    public function test_cdr_routes_require_login_and_use_signed_in_employee_as_requester(): void
    {
        $this->postJson('/api/controlled_document_requests', $this->payload())
            ->assertStatus(401)
            ->assertJsonPath('message', 'Token Not Found');

        $token = (new LoginController())->genToken(1, (object) [
            'id' => 1,
            'user_id' => 1,
            'username' => 'nattapol.srisuk',
            'employee_code' => 'MTLT2607',
            'permission_id' => 1,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/controlled_document_requests', array_merge(
                $this->payload(),
                ['requested_by' => 'SPOOFED-CODE']
            ));

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.requested_by', 'MTLT2607')
            ->assertJsonPath('data.cdr_no', '003/2026');
        $this->assertDatabaseHas('controlled_document_requests', [
            'requested_by' => 'MTLT2607',
            'create_by' => 'MTLT2607',
        ]);
    }

    private function request(array $overrides = []): Request
    {
        $request = Request::create('/api/controlled_document_requests', 'POST', array_merge(
            $this->payload(),
            $overrides
        ));
        $request->merge([
            'login_by' => (object) ['employee_code' => 'CRC'],
        ]);

        return $request;
    }

    private function payload(): array
    {
        return [
            'to' => 'JSA, John Stewart Anderson',
            'from' => 'CRC, Example Creator',
            'date' => '2026-07-22',
            'categories' => 'ims',
            'request_for' => 'addition,amendment',
            'document_name' => 'Controlled document',
            'current_revision' => 'Rev. 1',
            'reason_description' => 'Required update',
            'effective_date_purpose' => '2026-07-25',
            'requested_by' => 'SPOOFED-CODE',
            'requested_date' => '2026-07-22',
            'review_comments' => '-',
            'reviewed_by' => 'REV001',
            'reviewed_by_status' => 'pending',
            'approval_comments' => '-',
            'approved_by' => 'APP001',
            'approved_by_status' => 'pending',
            'acknowledged_by' => 'ACT001',
            'acknowledged_by_status' => 'pending',
            'attachments' => ['/uploads/files/evidence.pdf'],
        ];
    }

    private function controller(): ControlledDocumentRequestsController
    {
        return new class extends ControlledDocumentRequestsController {
            public function Log($userId, $description, $type)
            {
                // Audit persistence is outside this controller-focused test.
            }
        };
    }
}
