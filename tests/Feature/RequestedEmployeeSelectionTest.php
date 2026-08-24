<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RequestedEmployeeSelectionTest extends TestCase
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

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('employees')->insert([
            'code' => 'EMP001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_selected_requester_must_be_an_existing_employee(): void
    {
        $this->assertSame('EMP001', $this->resolve(['requested_by' => ' EMP001 '], 'ACTOR'));
        $this->assertNull($this->resolve(['requested_by' => 'UNKNOWN'], 'ACTOR'));
        $this->assertNull($this->resolve(['requested_by' => ''], 'ACTOR'));
    }

    public function test_requester_falls_back_to_actor_for_legacy_clients(): void
    {
        $this->assertSame('ACTOR', $this->resolve([], 'ACTOR'));
    }

    private function resolve(array $payload, string $fallback): ?string
    {
        $method = new ReflectionMethod(Controller::class, 'resolveRequestedEmployeeCode');
        $method->setAccessible(true);

        return $method->invoke(
            new Controller(),
            Request::create('/api/test', 'POST', $payload),
            $fallback
        );
    }
}
