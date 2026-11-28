<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siklus_kolams', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('urutan');
            $table->unsignedInteger('jumlah_tebar');
            $table->decimal('target_bobot_g', 8, 2);
            $table->enum('status', ['pending', 'aktif', 'selesai'])->default('pending');
            $table->dateTime('tanggal_mulai')->nullable();
            $table->dateTime('tanggal_selesai')->nullable();
            $table->decimal('bobot_akhir_g', 8, 2)->nullable();
            $table->decimal('biomassa_akhir_g', 12, 2)->nullable();
            $table->unsignedInteger('jumlah_mati')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('kolam_id')
                ->references('id')
                ->on('kolams')
                ->cascadeOnDelete();
            $table->index(['kolam_id', 'status']);
            $table->unique(['kolam_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siklus_kolams');
    }
};
