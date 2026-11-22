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
        Schema::create('keadaan_zonas', function (Blueprint $table) {
            $table->unsignedInteger('zona_id')->primary();
            $table->unsignedInteger('kolam_id');
            $table->decimal('suhu_asli', 7, 3);
            $table->decimal('ph_asli', 7, 3);
            $table->decimal('do_asli', 7, 3);
            $table->decimal('amonia_asli', 7, 3);
            $table->decimal('sisa_pakan_g', 10, 3)->default(0);
            $table->dateTime('diperbarui_pada');

            $table->foreign('zona_id')
                ->references('id')
                ->on('zona_kolams')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keadaan_zonas');
    }
};
