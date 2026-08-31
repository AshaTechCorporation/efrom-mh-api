<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeeSheetSchemaRepairMigrationTest extends TestCase
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

        Schema::create('fee_sheet_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('approved_by_ch_id')->nullable();
        });
    }

    public function test_repair_adds_missing_workflow_columns_to_legacy_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('fee_sheet_revisions', 'status'));
        $this->assertFalse(Schema::hasColumn('fee_sheet_revisions', 'approved_by_ch_status'));

        require_once database_path('migrations/2026_08_31_000001_repair_fee_sheet_revision_workflow_columns.php');

        (new \RepairFeeSheetRevisionWorkflowColumns())->up();

        $this->assertTrue(Schema::hasColumn('fee_sheet_revisions', 'status'));
        $this->assertTrue(Schema::hasColumn('fee_sheet_revisions', 'approved_by_ch_status'));
    }
}
