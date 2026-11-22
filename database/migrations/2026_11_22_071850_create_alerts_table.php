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
        Schema::create('alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('kolam_id');
            $table->unsignedInteger('zona_id')->nullable();
            $table->unsignedInteger('aturan_id');
            $table->string('parameter', 20);
            $table->decimal('nilai', 10, 3);
            $table->enum('tingkat', ['aman', 'waspada', 'kritis']);
            $table->dateTime('waktu');
            $table->enum('status', ['baru', 'ditangani'])->default('baru');

            $table->index(
                ['kolam_id', 'parameter', 'tingkat', 'status', 'waktu'],
                'idx_alert'
            );

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
        Schema::dropIfExists('alerts');
    }
};
