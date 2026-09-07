<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AntiCorruptionImsMigrationTest extends TestCase
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

        foreach (['charitable_contributions', 'gift_hospitalities', 'gift_hospitality_offerings'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('id');
            });
        }
    }

    public function test_migration_adds_ims_workflow_columns_for_all_three_forms(): void
    {
        $migration = require database_path('migrations/2026_09_07_000001_add_ims_acknowledgement_to_anti_corruption_forms.php');
        $migration->up();

        foreach (['charitable_contributions', 'gift_hospitalities', 'gift_hospitality_offerings'] as $tableName) {
            $this->assertTrue(Schema::hasColumns($tableName, [
                'ims_acknowledged_by',
                'ims_acknowledged_by_status',
                'ims_acknowledged_by_date',
            ]));
        }
    }
}
