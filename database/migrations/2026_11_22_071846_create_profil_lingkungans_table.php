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
        Schema::create('profil_lingkungans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('zona_id');
            $table->string('parameter', 20)->comment('suhu|ph|do|amonia');
            $table->decimal('nilai_dasar', 8, 3)
                ->comment('suhu & pH: nilai dasar; do & amonia: nilai awal saja');
            $table->decimal('amplitudo_harian', 8, 3)->default(0)
                ->comment('dipakai suhu saja');
            $table->decimal('offset_zona', 8, 3)->default(0);

            $table->unique(['zona_id', 'parameter'], 'uq_profil');

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
        Schema::dropIfExists('profil_lingkungans');
    }
};
