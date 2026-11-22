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
        Schema::create('kolams', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->enum('jenis', ['terpal', 'beton', 'tanah'])->default('terpal');
            $table->decimal('panjang_m', 6, 2);
            $table->decimal('lebar_m', 6, 2);
            $table->decimal('tinggi_air_m', 5, 2);
            $table->decimal('volume_air_m3', 10, 3)
                ->storedAs('panjang_m * lebar_m * tinggi_air_m');
            $table->unsignedInteger('jumlah_ikan_awal');
            $table->decimal('bobot_awal_rata_g', 8, 2);
            $table->decimal('bobot_awal_sd_g', 8, 2)->default(0);
            $table->decimal('panjang_awal_rata_cm', 6, 2);
            $table->decimal('koef_a', 12, 8)
                ->comment('W = a * L^3, dihitung dari ukuran awal');
            $table->decimal('biomassa_awal_g', 12, 2)->nullable()
                ->comment('turunan: SUM bobot semua ikan saat dibuat');
            $table->decimal('padat_tebar_per_m3', 10, 2)->nullable()
                ->comment('turunan: jumlah ikan / volume');
            $table->decimal('dosis_harian_persen', 5, 2)->default(5.00)
                ->comment('% biomassa per hari');
            $table->decimal('bobot_target_panen_g', 8, 2)->default(100.00);
            $table->dateTime('waktu_mulai_sandbox');
            $table->dateTime('waktu_sandbox_sekarang');
            $table->decimal('kecepatan_waktu', 8, 2)->default(60)
                ->comment('menit virtual per 1 detik nyata');
            $table->enum('status', ['aktif', 'selesai'])->default('aktif');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kolams');
    }
};
