<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GiftHospitalityOfferingRoutingTest extends TestCase
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

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->nullable();
            $table->string('level_name')->nullable();
            $table->string('title_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('employee_type_name')->nullable();
            $table->boolean('is_approver')->nullable();
            $table->string('active')->nullable();
            $table->softDeletes();
        });
        Schema::create('committees', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('committee_employees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('committee_id');
            $table->string('employee_code');
            $table->softDeletes();
        });
        Schema::create('gift_hospitality_offerings', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type')->nullable();
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('receiver_name_and_company')->nullable();
            $table->dateTime('proposed_date')->nullable();
            foreach (['verified_by', 'ims_acknowledged_by', 'approved_by', 'approved_by_2', 'acknowledged_by'] as $field) {
                $table->string($field)->nullable();
                $table->string($field . '_status')->nullable();
                $table->dateTime($field . '_date')->nullable();
            }
            $table->text('attachments')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            ['code' => 'REQ', 'initial' => 'REQ', 'level_name' => 'EE'],
            ['code' => 'MJR', 'initial' => 'MJR', 'level_name' => 'EE'],
            ['code' => 'SS', 'initial' => 'SS', 'level_name' => 'DI'],
            ['code' => 'CH', 'initial' => 'CH', 'level_name' => 'MD'],
            ['code' => 'JN', 'initial' => 'JN', 'level_name' => 'SES'],
            ['code' => 'OTHER', 'initial' => 'OTHER', 'level_name' => 'SE'],
        ]);
        DB::table('committees')->insert([
            ['id' => 1, 'name' => 'ACSC'],
            ['id' => 2, 'name' => 'ACSL'],
        ]);
        DB::table('committee_employees')->insert([
            ['committee_id' => 1, 'employee_code' => 'MJR'],
            ['committee_id' => 2, 'employee_code' => 'SS'],
        ]);
    }

    public function test_new_offering_accepts_only_the_configured_workflow_assignees(): void
    {
        $valid = $this->payload();
        $this->withActor('REQ')
            ->postJson('/api/gift_hospitality_offerings', $valid)
            ->assertOk()
            ->assertJsonPath('data.verified_by', 'MJR')
            ->assertJsonPath('data.ims_acknowledged_by', 'SS')
            ->assertJsonPath('data.approved_by', 'CH')
            ->assertJsonPath('data.acknowledged_by', 'JN');

        foreach ([
            ['verified_by', 'OTHER'],
            ['ims_acknowledged_by', 'OTHER'],
            ['ims_acknowledged_by', null],
            ['approved_by', 'OTHER'],
            ['acknowledged_by', 'OTHER'],
            ['requested_by', 'OTHER'],
        ] as [$field, $code]) {
            $this->withActor('REQ')
                ->postJson('/api/gift_hospitality_offerings', array_merge($valid, [$field => $code]))
                ->assertStatus(422)
                ->assertJsonPath('status', false);
        }

        $this->assertSame(1, DB::table('gift_hospitality_offerings')->count());
    }

    public function test_update_rejects_invalid_routing_without_changing_existing_assignment(): void
    {
        $this->withActor('REQ')->postJson('/api/gift_hospitality_offerings', $this->payload())->assertOk();
        $id = DB::table('gift_hospitality_offerings')->value('id');

        $this->withActor('REQ')
            ->putJson('/api/gift_hospitality_offerings/' . $id, array_merge($this->payload(), [
                'ims_acknowledged_by' => 'OTHER',
            ]))
            ->assertStatus(422);

        $this->assertSame('SS', DB::table('gift_hospitality_offerings')->where('id', $id)->value('ims_acknowledged_by'));
    }

    public function test_requester_dropdown_only_returns_ee_level_and_above(): void
    {
        $this->getJson('/api/employees?workflow_role=ee_above&limit=20')
            ->assertOk()
            ->assertJsonFragment(['code' => 'REQ'])
            ->assertJsonMissing(['code' => 'OTHER']);
    }

    private function payload(): array
    {
        return [
            'requested_by' => 'REQ',
            'request_type' => 'gift',
            'description' => 'Test offering',
            'purpose' => 'Test routing',
            'value' => 12000,
            'receiver_name_and_company' => 'Test receiver',
            'proposed_date' => '2026-09-23',
            'verified_by' => 'MJR',
            'ims_acknowledged_by' => 'SS',
            'approved_by' => 'CH',
            'acknowledged_by' => 'JN',
        ];
    }

    private function withActor(string $code): self
    {
        $now = time();
        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 1,
            'lun' => (object) ['id' => 1, 'username' => strtolower($code), 'employee_code' => $code],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], 'key');

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}
