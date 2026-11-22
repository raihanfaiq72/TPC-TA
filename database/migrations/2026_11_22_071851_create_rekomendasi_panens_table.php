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
        Schema::create('rekomendasi_panens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->dateTime('tanggal_evaluasi')->comment('jam virtual sandbox');
            $table->decimal('bobot_rata_g', 8, 3);
            $table->decimal('skor_bobot', 5, 2);
            $table->decimal('skor_air', 5, 2);
            $table->decimal('skor_kesiapan', 5, 2);
            $table->decimal('estimasi_hari_ke_target', 6, 1)->nullable();
            $table->enum('status', ['belum siap', 'hampir siap', 'siap panen']);
            $table->text('alasan');

            $table->index(['kolam_id', 'tanggal_evaluasi'], 'idx_rekom');

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
        Schema::dropIfExists('rekomendasi_panens');
    }
};
