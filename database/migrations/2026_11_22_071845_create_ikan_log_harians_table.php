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
        Schema::create('ikan_log_harians', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ikan_id');
            $table->date('tanggal_sandbox');
            $table->decimal('bobot_g', 10, 3);
            $table->decimal('panjang_cm', 7, 3);
            $table->decimal('sgr_hari_persen', 8, 4);
            $table->decimal('jam_di_atas', 5, 2);
            $table->decimal('jam_di_bawah', 5, 2);
            $table->decimal('suhu_terpapar', 7, 3);
            $table->decimal('ph_terpapar', 7, 3);
            $table->decimal('do_terpapar', 7, 3);
            $table->decimal('amonia_terpapar', 7, 3);
            $table->decimal('pakan_dimakan_g', 10, 3);
            $table->decimal('faktor_ransum', 5, 3);
            $table->decimal('stres', 5, 2);

            $table->unique(['ikan_id', 'tanggal_sandbox'], 'uq_log_harian');

            $table->foreign('ikan_id')
                ->references('id')
                ->on('ikans')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ikan_log_harians');
    }
};
