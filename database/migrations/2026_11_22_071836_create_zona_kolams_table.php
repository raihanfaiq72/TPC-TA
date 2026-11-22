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
        Schema::create('zona_kolams', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kolam_id');
            $table->enum('nama', ['atas', 'bawah']);
            $table->integer('kedalaman_cm');
            $table->decimal('porsi_volume', 3, 2)
                ->comment('bagian volume air: atas 0.40, bawah 0.60');

            $table->unique(['kolam_id', 'nama'], 'uq_zona');

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
        Schema::dropIfExists('zona_kolams');
    }
};
