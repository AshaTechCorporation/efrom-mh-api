<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRevisionFieldsToFeeAgreements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fee_sheet_revisions', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('comment');
        });

        Schema::table('fee_agreements', function (Blueprint $table) {
            $table->string('revision_label')->nullable()->after('revision_no');
            $table->string('revision_name')->nullable()->after('revision_label');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fee_sheet_revisions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('fee_agreements', function (Blueprint $table) {
            $table->dropColumn(['revision_label', 'revision_name']);
        });
    }
}
