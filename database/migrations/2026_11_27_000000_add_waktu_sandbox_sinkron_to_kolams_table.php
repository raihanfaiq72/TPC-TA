<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waktu virtual sekarang harus tetap berjalan realtime meskipun
     * simulasi tidak sedang dipercepat. Kolom ini menyimpan jam nyata
     * yang menjadi acuan perhitungan tersebut.
     */
    public function up(): void
    {
        Schema::table('kolams', function (Blueprint $table) {
            $table->dateTime('waktu_sandbox_sinkron')
                ->nullable()
                ->after('waktu_sandbox_sekarang');
        });
    }

    public function down(): void
    {
        Schema::table('kolams', function (Blueprint $table) {
            $table->dropColumn('waktu_sandbox_sinkron');
        });
    }
};
