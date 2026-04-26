<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeSignatureActionRequestDatesToDatetimeInConceptDesignReviewsTable extends Migration
{
    public function up()
    {
        Schema::table('concept_design_reviews', function (Blueprint $table) {
            $table->dateTime('reviewed_by_date')->nullable()->change();
            $table->dateTime('responded_by_date')->nullable()->change();
            $table->dateTime('signed_by_tl_date')->nullable()->change();
            $table->dateTime('signed_by_tl2_date')->nullable()->change();
            $table->dateTime('acknowledged_by_date')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('concept_design_reviews', function (Blueprint $table) {
            $table->date('reviewed_by_date')->nullable()->change();
            $table->date('responded_by_date')->nullable()->change();
            $table->date('signed_by_tl_date')->nullable()->change();
            $table->date('signed_by_tl2_date')->nullable()->change();
            $table->date('acknowledged_by_date')->nullable()->change();
        });
    }
}
