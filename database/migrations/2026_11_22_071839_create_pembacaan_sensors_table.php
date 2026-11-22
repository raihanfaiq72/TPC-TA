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
        Schema::create('pembacaan_sensors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('perangkat_id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('zona_id')->nullable();
            $table->string('parameter', 20);
            $table->decimal('nilai', 10, 3)->nullable()
                ->comment('NULL bila kualitas_data = gagal');
            $table->string('satuan', 10);
            $table->dateTime('waktu_ukur');
            $table->dateTime('waktu_diterima');
            $table->enum('kualitas_data', ['normal', 'outlier', 'gagal'])->default('normal');
            $table->unsignedTinyInteger('baterai_persen')->nullable();
            $table->smallInteger('sinyal_rssi')->nullable();

            $table->index(
                ['kolam_id', 'zona_id', 'parameter', 'waktu_ukur'],
                'idx_baca_kolam'
            );
            $table->index(['perangkat_id', 'waktu_ukur'], 'idx_baca_perangkat');

            $table->foreign('perangkat_id')
                ->references('id')
                ->on('perangkats')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembacaan_sensors');
    }
};
