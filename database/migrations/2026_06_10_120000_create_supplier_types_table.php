<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSupplierTypesTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('detail')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('supplier_types')->insert([
            [
                'code' => 'INSTRUMENTS_CALIBRATION',
                'name' => 'Instruments and Calibration Services',
                'detail' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SOFTWARE',
                'name' => 'Software',
                'detail' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('supplier_types');
    }
}
