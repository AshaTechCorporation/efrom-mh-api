<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CharitableContributionRoutingTest extends TestCase
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
        Schema::create('charitable_contributions', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type')->nullable();
            $table->text('event_description')->nullable();
            $table->text('event_purpose')->nullable();
            $table->string('organizer_name')->nullable();
            $table->text('contribution_description')->nullable();
            $table->decimal('value_amount', 12, 2)->nullable();
            $table->string('currency_code')->nullable();
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->dateTime('proposed_date')->nullable();
            foreach (['acsc_by', 'ims_acknowledged_by', 'approver_by', 'approver_by_2', 'acsl_by'] as $field) {
                $table->string($field)->nullable();
                $table->string($field . '_status')->nullable();
                $table->dateTime($field . '_date')->nullable();
            }
            $table->string('status')->nullable();
            $table->string('create_by')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            ['code' => 'REQ', 'initial' => 'REQ', 'firstname' => 'Request', 'lastname' => 'User', 'level_name' => 'EE'],
            ['code' => 'MJR', 'initial' => 'MJR', 'firstname' => 'Sarinlada', 'lastname' => 'Sirimonthonrat', 'level_name' => 'EE'],
            ['code' => 'SS', 'initial' => 'SS', 'firstname' => 'Sasiporn', 'lastname' => 'Sirilatthaporn', 'level_name' => 'DI'],
            ['code' => 'CH', 'initial' => 'CH', 'firstname' => 'Chen', 'lastname' => 'YaoHui', 'level_name' => 'MD'],
            ['code' => 'DI2', 'initial' => 'DI2', 'firstname' => 'Second', 'lastname' => 'Director', 'level_name' => 'DI'],
            ['code' => 'JN', 'initial' => 'JN', 'firstname' => 'Jarussri', 'lastname' => 'Suwitchanpan', 'level_name' => 'SES'],
            ['code' => 'OTHER', 'initial' => 'OTHER', 'firstname' => 'Other', 'lastname' => 'Employee', 'level_name' => 'SE'],
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

    public function test_create_accepts_only_configured_charitable_workflow_assignments(): void
    {
        $valid = $this->payload();
        $this->withActor('REQ')
            ->postJson('/api/charitable_contributions', $valid)
            ->assertOk()
            ->assertJsonPath('data.acsc_by', 'MJR')
            ->assertJsonPath('data.ims_acknowledged_by', 'SS')
            ->assertJsonPath('data.approver_by', 'CH')
            ->assertJsonPath('data.acsl_by', 'JN');

        foreach ([
            ['acsc_by', 'OTHER'],
            ['ims_acknowledged_by', 'OTHER'],
            ['ims_acknowledged_by', null],
            ['approver_by', 'OTHER'],
            ['approver_by_2', 'CH'],
            ['acsl_by', 'OTHER'],
            ['requested_by', 'OTHER'],
        ] as [$field, $code]) {
            $this->withActor('REQ')
                ->postJson('/api/charitable_contributions', array_merge($valid, [$field => $code]))
                ->assertStatus(422)
                ->assertJsonPath('status', false);
        }

        $this->assertSame(1, DB::table('charitable_contributions')->count());
    }

    public function test_charitable_workflow_runs_acsl_before_approval_and_accounts_jn_last(): void
    {
        $this->withActor('REQ')->postJson('/api/charitable_contributions', $this->payload())->assertOk();
        $id = DB::table('charitable_contributions')->value('id');

        $steps = [
            ['MJR', 'acsc_by_status'],
            ['SS', 'ims_acknowledged_by_status'],
            ['CH', 'approver_by_status'],
            ['JN', 'acsl_by_status'],
        ];

        $this->withActor('CH')
            ->patchJson("/api/charitable_contributions/{$id}/actions/approver_by_status", ['decision' => 'approved'])
            ->assertStatus(409);

        foreach ($steps as [$actor, $type]) {
            $this->withActor($actor)
                ->patchJson("/api/charitable_contributions/{$id}/actions/{$type}", ['decision' => 'approved'])
                ->assertStatus(201)
                ->assertJsonPath('status', true);
        }
    }

    public function test_show_returns_names_without_replacing_employee_codes(): void
    {
        $this->withActor('REQ')->postJson('/api/charitable_contributions', $this->payload())->assertOk();
        $id = DB::table('charitable_contributions')->value('id');

        $this->getJson('/api/charitable_contributions/' . $id)
            ->assertOk()
            ->assertJsonPath('data.acsc_by', 'MJR')
            ->assertJsonPath('data.acsc_by_name', 'MJR, Sarinlada Sirimonthonrat')
            ->assertJsonPath('data.ims_acknowledged_by_name', 'SS, Sasiporn Sirilatthaporn')
            ->assertJsonPath('data.approver_by_name', 'CH, Chen YaoHui')
            ->assertJsonPath('data.acsl_by_name', 'JN, Jarussri Suwitchanpan');
    }

    private function payload(): array
    {
        return [
            'requested_by' => 'REQ',
            'request_type' => 'charitable_contribution',
            'event_description' => 'Test charitable contribution event',
            'event_purpose' => 'Test routing',
            'organizer_name' => 'Test organizer',
            'contribution_description' => 'Test contribution',
            'value_amount' => 12000,
            'currency_code' => 'THB',
            'proposed_date' => '2026-09-24',
            'acsc_by' => 'MJR',
            'ims_acknowledged_by' => 'SS',
            'approver_by' => 'CH',
            'acsl_by' => 'JN',
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
