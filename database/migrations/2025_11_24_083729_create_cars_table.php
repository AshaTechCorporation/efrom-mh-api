<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->increments('id'); // INT unsigned auto increment

            // ------- ข้อมูลหลักตาม JSON -------
            $table->string('department', 255)->charset('utf8')->nullable();
            $table->string('project_name', 255)->charset('utf8')->nullable();
            $table->string('ref_no', 100)->charset('utf8')->nullable();
            $table->string('project_no', 100)->charset('utf8')->nullable();
            $table->string('to', 255)->charset('utf8')->nullable();

            $table->date('date')->nullable();

            $table->string('car_issued_by', 255)->charset('utf8')->nullable();

            // sources (array) → เก็บ JSON string ใน text (สไตล์คุณ)
            $table->text('sources')->nullable();

            $table->string('other_source_description', 255)->charset('utf8')->nullable();

            $table->string('severity', 50)->charset('utf8')->nullable();

            // array → text JSON
            $table->text('non_conformity_types')->nullable();

            $table->text('non_conformity_description')->nullable();

            // FK style ของคุณ
            $table->unsignedInteger('responsible_person_id')->nullable()->index();
            $table->unsignedInteger('imr_id')->nullable()->index();

            // ผู้สร้าง/ผู้แก้ไข
            $table->string('create_by', 100)->charset('utf8')->nullable();
            $table->string('update_by', 100)->charset('utf8')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cars');
    }
}
