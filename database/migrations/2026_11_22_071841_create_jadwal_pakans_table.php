<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jadwal_pakans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('jenis_pakan_id');
            $table->time('jam');
            $table->decimal('porsi_dosis_persen', 5, 2)
                ->comment('bagian dari dosis harian (total semua baris = 100)');
            $table->boolean('aktif')->default(true);

            $table->foreign('kolam_id')
                ->references('id')
                ->on('kolams')
                ->cascadeOnDelete();
            $table->foreign('jenis_pakan_id')
                ->references('id')
                ->on('jenis_pakans')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_pakans');
    }
};
