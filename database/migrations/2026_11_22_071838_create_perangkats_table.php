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
        Schema::create('perangkats', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 40)->unique();
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('zona_id')->nullable()
                ->comment('NULL untuk perangkat tingkat kolam (feeder, level)');
            $table->unsignedInteger('jenis_perangkat_id');
            $table->enum('status', ['aktif', 'mati', 'error'])->default('aktif');
            $table->integer('interval_menit');
            $table->dateTime('terakhir_baca')->nullable()
                ->comment('jam virtual pembacaan terakhir');
            $table->decimal('baterai_persen', 5, 2)->default(100);
            $table->date('tanggal_kalibrasi')->nullable();

            $table->foreign('kolam_id')
                ->references('id')
                ->on('kolams')
                ->cascadeOnDelete();
            $table->foreign('zona_id')
                ->references('id')
                ->on('zona_kolams')
                ->cascadeOnDelete();
            $table->foreign('jenis_perangkat_id')
                ->references('id')
                ->on('jenis_perangkats')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perangkats');
    }
};
