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
        Schema::create('jenis_pakans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('merek', 60);
            $table->enum('bentuk', ['apung', 'tenggelam'])->default('apung');
            $table->decimal('protein_persen', 5, 2);
            $table->decimal('lemak_persen', 5, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_pakans');
    }
};
