<?php

namespace Tests\Feature;

use Database\Seeders\ProjectTypeSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectTypeSettingsTest extends TestCase
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

        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('detail')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function test_seed_creates_initial_project_types_without_duplicates(): void
    {
        $this->seed(ProjectTypeSeeder::class);
        $this->seed(ProjectTypeSeeder::class);

        $this->assertDatabaseCount('project_types', 16);
        $this->assertSame(1, DB::table('project_types')->where('code', 'A')->count());
        $this->assertDatabaseHas('project_types', [
            'code' => 'A',
            'name' => 'Commercial-Office',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('project_types', [
            'code' => 'V',
            'name' => 'PM/CM',
            'is_active' => 1,
        ]);
    }

    public function test_get_project_types_returns_only_active_rows_ordered_by_code(): void
    {
        DB::table('project_types')->insert([
            $this->projectTypeRow('B', 'Commercial-General', 1),
            $this->projectTypeRow('A', 'Commercial-Office', 1),
            $this->projectTypeRow('C', 'Industrial', 0),
        ]);

        $response = $this->getJson('/api/get_project_types');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'A')
            ->assertJsonPath('data.1.code', 'B');
    }

    public function test_seed_deactivates_known_legacy_non_project_type_codes(): void
    {
        DB::table('project_types')->insert([
            $this->projectTypeRow('001', 'Instruments and Calibration Services', 1),
            $this->projectTypeRow('A', 'Commercial-Office', 1),
        ]);

        $this->seed(ProjectTypeSeeder::class);

        $this->assertDatabaseHas('project_types', [
            'code' => '001',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('project_types', [
            'code' => 'A',
            'name' => 'Commercial-Office',
            'is_active' => 1,
        ]);
    }

    public function test_delete_sets_project_type_inactive(): void
    {
        $id = DB::table('project_types')->insertGetId(
            $this->projectTypeRow('A', 'Commercial-Office', 1)
        );

        $response = $this->deleteJson("/api/project_types/{$id}");

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('project_types', [
            'id' => $id,
            'code' => 'A',
            'is_active' => 0,
        ]);
    }

    private function projectTypeRow(string $code, string $name, int $isActive): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'detail' => null,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
