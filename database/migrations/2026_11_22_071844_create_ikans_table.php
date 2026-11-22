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
        Schema::create('ikans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('kolam_id');
            $table->string('kode', 30);
            $table->decimal('faktor_genetik', 5, 3);
            $table->decimal('preferensi_zona', 4, 3)
                ->comment('0 = suka bawah, 1 = suka atas');
            $table->decimal('bobot_awal_g', 10, 3);
            $table->decimal('panjang_awal_cm', 7, 3);
            $table->decimal('bobot_g', 10, 3);
            $table->decimal('panjang_cm', 7, 3);
            $table->unsignedInteger('zona_id');
            $table->decimal('isi_lambung_persen', 5, 2)->default(0);
            $table->decimal('stres', 5, 2)->default(0);
            $table->enum('status', ['hidup', 'mati'])->default('hidup');
            $table->date('tanggal_mati')->nullable();
            $table->string('penyebab_mati', 40)->nullable();

            // Akumulator kerja harian (di-reset setelah proses harian).
            $table->unsignedSmallInteger('hari_tick_atas')->default(0);
            $table->unsignedSmallInteger('hari_tick_bawah')->default(0);
            $table->double('hari_sum_suhu')->default(0);
            $table->double('hari_sum_ph')->default(0);
            $table->double('hari_sum_do')->default(0);
            $table->double('hari_sum_amonia')->default(0);
            $table->double('hari_pakan_g')->default(0);

            $table->unique(['kolam_id', 'kode'], 'uq_ikan_kode');
            $table->index(['kolam_id', 'status', 'zona_id'], 'idx_ikan_kolam_status_zona');

            $table->foreign('kolam_id')
                ->references('id')
                ->on('kolams')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ikans');
    }
};
