<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConceptDesignReviewDiscussionTopicsTable extends Migration
{
    public function up()
    {
        Schema::create('concept_design_review_discussion_topics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('concept_design_review_id');
            $table->unsignedInteger('topic_no')->default(1);
            $table->string('author_code', 100)->nullable();
            $table->string('author_name', 255)->nullable();
            $table->string('author_role', 50)->default('reviewer');
            $table->longText('message');
            $table->string('status', 50)->default('open');
            $table->longText('attachments')->nullable();
            $table->string('create_by', 100)->nullable();
            $table->string('update_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('concept_design_review_id', 'cdr_discussion_topics_review_idx');
            $table->index('topic_no', 'cdr_discussion_topics_no_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('concept_design_review_discussion_topics');
    }
}
