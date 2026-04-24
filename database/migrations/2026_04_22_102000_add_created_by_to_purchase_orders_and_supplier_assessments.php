<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToPurchaseOrdersAndSupplierAssessments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'purchase_orders_created_by_idx');
                }
            });

            DB::table('purchase_orders')
                ->whereNull('created_by')
                ->whereNotNull('create_by')
                ->update(['created_by' => DB::raw('create_by')]);
        }

        if (Schema::hasTable('supplier_assessments')) {
            Schema::table('supplier_assessments', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_assessments', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'supplier_assessments_created_by_idx');
                }
            });

            DB::table('supplier_assessments')
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
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_orders', 'created_by')) {
                    try {
                        $table->dropIndex('purchase_orders_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('supplier_assessments')) {
            Schema::table('supplier_assessments', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_assessments', 'created_by')) {
                    try {
                        $table->dropIndex('supplier_assessments_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }
    }
}
