<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToSupplierEvaluationsAndSubConsultantAssessments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('supplier_evaluations')) {
            Schema::table('supplier_evaluations', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_evaluations', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'supplier_evaluations_created_by_idx');
                }
            });

            DB::table('supplier_evaluations')
                ->whereNull('created_by')
                ->whereNotNull('create_by')
                ->update(['created_by' => DB::raw('create_by')]);
        }

        if (Schema::hasTable('sub_consultant_assessments')) {
            Schema::table('sub_consultant_assessments', function (Blueprint $table) {
                if (!Schema::hasColumn('sub_consultant_assessments', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'sub_consultant_assessments_created_by_idx');
                }
            });

            DB::table('sub_consultant_assessments')
                ->whereNull('created_by')
                ->whereNotNull('create_by')
                ->update(['created_by' => DB::raw('create_by')]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('supplier_evaluations')) {
            Schema::table('supplier_evaluations', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_evaluations', 'created_by')) {
                    try {
                        $table->dropIndex('supplier_evaluations_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('sub_consultant_assessments')) {
            Schema::table('sub_consultant_assessments', function (Blueprint $table) {
                if (Schema::hasColumn('sub_consultant_assessments', 'created_by')) {
                    try {
                        $table->dropIndex('sub_consultant_assessments_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }
    }
}
