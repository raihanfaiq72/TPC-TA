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
        Schema::create('sampling_pertumbuhans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->date('tanggal')->comment('tanggal virtual sandbox');
            $table->unsignedInteger('jumlah_sampel');
            $table->decimal('bobot_total_sampel_g', 10, 2);
            $table->decimal('bobot_rata_g', 8, 3)
                ->storedAs('bobot_total_sampel_g * 1.0 / jumlah_sampel');
            $table->decimal('panjang_rata_cm', 6, 2);
            $table->timestamp('dibuat_pada')->useCurrent();

            $table->index(['kolam_id', 'tanggal'], 'idx_sampling');

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
        Schema::dropIfExists('sampling_pertumbuhans');
    }
};
