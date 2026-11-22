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
        Schema::create('skenario_simulasis', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->enum('jenis_kejadian', [
                'hujan_malam',
                'ganti_air',
                'lonjakan_amonia',
                'aerator_mati',
            ]);
            $table->decimal('besar_dampak', 8, 3);
            $table->dateTime('mulai');
            $table->dateTime('selesai');
            $table->boolean('sudah_diterapkan')->default(false);

            $table->index(['kolam_id', 'mulai', 'selesai'], 'idx_skenario');

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
        Schema::dropIfExists('skenario_simulasis');
    }
};
