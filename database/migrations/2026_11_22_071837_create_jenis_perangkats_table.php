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
        Schema::create('jenis_perangkats', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 20)->unique();
            $table->string('nama', 60);
            $table->string('parameter', 20);
            $table->string('satuan', 10);
            $table->decimal('nilai_min_fisik', 10, 3)->nullable();
            $table->decimal('nilai_maks_fisik', 10, 3)->nullable();
            $table->integer('interval_default_menit');
            $table->enum('tingkat', ['zona', 'kolam']);
            $table->decimal('noise_sd', 8, 4)->default(0)
                ->comment('simpangan baku noise sensor');
            $table->decimal('peluang_error', 5, 4)->default(0.0050)
                ->comment('peluang outlier/gagal per pembacaan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_perangkats');
    }
};
