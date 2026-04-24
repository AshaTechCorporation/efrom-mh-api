<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToPurchaseRequisitionsAndDesignReviews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_requisitions', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'purchase_requisitions_created_by_idx');
                }
            });

            DB::table('purchase_requisitions')
                ->whereNull('created_by')
                ->whereNotNull('create_by')
                ->update(['created_by' => DB::raw('create_by')]);
        }

        if (Schema::hasTable('design_reviews')) {
            Schema::table('design_reviews', function (Blueprint $table) {
                if (!Schema::hasColumn('design_reviews', 'created_by')) {
                    $table->string('created_by', 100)->nullable()->after('create_by');
                    $table->index('created_by', 'design_reviews_created_by_idx');
                }
            });

            DB::table('design_reviews')
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
        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_requisitions', 'created_by')) {
                    try {
                        $table->dropIndex('purchase_requisitions_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('design_reviews')) {
            Schema::table('design_reviews', function (Blueprint $table) {
                if (Schema::hasColumn('design_reviews', 'created_by')) {
                    try {
                        $table->dropIndex('design_reviews_created_by_idx');
                    } catch (\Throwable $e) {
                        // noop
                    }
                    $table->dropColumn('created_by');
                }
            });
        }
    }
}
