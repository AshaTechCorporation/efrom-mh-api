<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddScopeActionColumnsToMenuPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menu_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_permissions', 'create')) {
                $table->tinyInteger('create')->default(0)->after('menu_id');
            }
            if (!Schema::hasColumn('menu_permissions', 'view_own')) {
                $table->tinyInteger('view_own')->default(0)->after('create');
            }
            if (!Schema::hasColumn('menu_permissions', 'view_all')) {
                $table->tinyInteger('view_all')->default(0)->after('view_own');
            }
            if (!Schema::hasColumn('menu_permissions', 'edit_own')) {
                $table->tinyInteger('edit_own')->default(0)->after('view_all');
            }
            if (!Schema::hasColumn('menu_permissions', 'edit_all')) {
                $table->tinyInteger('edit_all')->default(0)->after('edit_own');
            }
            if (!Schema::hasColumn('menu_permissions', 'delete_own')) {
                $table->tinyInteger('delete_own')->default(0)->after('edit_all');
            }
            if (!Schema::hasColumn('menu_permissions', 'delete_all')) {
                $table->tinyInteger('delete_all')->default(0)->after('delete_own');
            }
        });

        // Legacy -> new mapping for non-breaking migration.
        if (Schema::hasColumn('menu_permissions', 'view')) {
            DB::table('menu_permissions')->where('view', 1)->update(['view_all' => 1]);
        }
        if (Schema::hasColumn('menu_permissions', 'edit')) {
            DB::table('menu_permissions')->where('edit', 1)->update(['edit_all' => 1]);
        }
        if (Schema::hasColumn('menu_permissions', 'delete')) {
            DB::table('menu_permissions')->where('delete', 1)->update(['delete_all' => 1]);
        }
        if (Schema::hasColumn('menu_permissions', 'save')) {
            DB::table('menu_permissions')->where('save', 1)->update(['create' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menu_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('menu_permissions', 'create')) {
                $table->dropColumn('create');
            }
            if (Schema::hasColumn('menu_permissions', 'view_own')) {
                $table->dropColumn('view_own');
            }
            if (Schema::hasColumn('menu_permissions', 'view_all')) {
                $table->dropColumn('view_all');
            }
            if (Schema::hasColumn('menu_permissions', 'edit_own')) {
                $table->dropColumn('edit_own');
            }
            if (Schema::hasColumn('menu_permissions', 'edit_all')) {
                $table->dropColumn('edit_all');
            }
            if (Schema::hasColumn('menu_permissions', 'delete_own')) {
                $table->dropColumn('delete_own');
            }
            if (Schema::hasColumn('menu_permissions', 'delete_all')) {
                $table->dropColumn('delete_all');
            }
        });
    }
}

