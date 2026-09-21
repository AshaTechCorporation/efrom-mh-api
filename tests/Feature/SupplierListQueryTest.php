<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierListQueryTest extends TestCase
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

        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('id');
            foreach (['type', 'name', 'status', 'address', 'phone', 'email', 'contact_person', 'create_by', 'update_by'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_supplier_search_sort_and_pagination_have_a_stable_tie_breaker(): void
    {
        foreach (range(1, 3) as $index) {
            DB::table('suppliers')->insert([
                'type' => 'Vendor',
                'name' => 'Same Supplier',
                'status' => 'Active',
                'email' => 'stable-' . $index . '@example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('suppliers')->insert([
            'type' => 'Vendor',
            'name' => 'Excluded Supplier',
            'status' => 'Inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = [
            'order' => [['column' => 2, 'dir' => 'asc']],
            'start' => 0,
            'length' => 2,
            'search' => ['value' => 'Same Supplier', 'regex' => false],
            'status' => 'Active',
        ];

        $first = $this->postJson('/api/suppliers_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(2, 'data.data');
        $request['start'] = 2;
        $second = $this->postJson('/api/suppliers_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(1, 'data.data');

        $this->assertSame([3, 2, 1], array_merge(
            collect($first->json('data.data'))->pluck('id')->all(),
            collect($second->json('data.data'))->pluck('id')->all()
        ));
    }
}
