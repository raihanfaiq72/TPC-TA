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
        Schema::create('log_pakans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('perangkat_id')->nullable()
                ->comment('feeder');
            $table->unsignedInteger('jadwal_id')->nullable();
            $table->unsignedInteger('jenis_pakan_id');
            $table->dateTime('waktu');
            $table->decimal('jumlah_g', 10, 2);
            $table->enum('pemicu', ['jadwal', 'manual'])->default('jadwal');

            $table->index(['kolam_id', 'waktu'], 'idx_logpakan');

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
        Schema::dropIfExists('log_pakans');
    }
};
