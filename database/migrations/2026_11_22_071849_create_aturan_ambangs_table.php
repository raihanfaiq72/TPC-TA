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
        Schema::create('aturan_ambangs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('parameter', 20);
            $table->enum('zona', ['atas', 'bawah'])->nullable()
                ->comment('NULL = semua zona');
            $table->decimal('batas_min', 10, 3)->nullable();
            $table->decimal('batas_maks', 10, 3)->nullable();
            $table->enum('tingkat', ['aman', 'waspada', 'kritis']);
            $table->string('sumber_rujukan', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aturan_ambangs');
    }
};
