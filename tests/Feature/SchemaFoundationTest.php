<?php

use App\Models\AturanAmbang;
use App\Models\JenisPakan;
use App\Models\JenisPerangkat;
use App\Models\Kolam;
use App\Models\SamplingPertumbuhan;
use App\Models\ZonaKolam;
use Database\Seeders\AturanAmbangSeeder;
use Database\Seeders\JenisPakanSeeder;
use Database\Seeders\JenisPerangkatSeeder;

test('master seeders fill the reference tables', function () {
    $this->seed([
        JenisPerangkatSeeder::class,
        JenisPakanSeeder::class,
        AturanAmbangSeeder::class,
    ]);

    expect(JenisPerangkat::count())->toBe(6)
        ->and(JenisPakan::count())->toBe(1)
        ->and(AturanAmbang::count())->toBe(16);

    $doKritis = AturanAmbang::query()
        ->where('parameter', 'do')
        ->where('tingkat', 'kritis')
        ->sole();

    expect($doKritis->batas_maks)->toBe(3.0)
        ->and($doKritis->batas_min)->toBeNull();
});

test('kolam stores a generated volume and relates to its zones', function () {
    $kolam = Kolam::create([
        'kode' => 'C07',
        'nama' => 'Kolam C07',
        'panjang_m' => 3,
        'lebar_m' => 2,
        'tinggi_air_m' => 1.5,
        'jumlah_ikan_awal' => 1000,
        'bobot_awal_rata_g' => 1.2,
        'panjang_awal_rata_cm' => 3.5,
        'koef_a' => 0.0123,
        'waktu_mulai_sandbox' => now(),
        'waktu_sandbox_sekarang' => now(),
    ]);

    expect($kolam->refresh()->volume_air_m3)->toBe(9.0);

    $kolam->zonas()->create([
        'nama' => 'atas',
        'kedalaman_cm' => 60,
        'porsi_volume' => 0.4,
    ]);

    expect($kolam->zonas)->toHaveCount(1)
        ->and($kolam->zonas->first())->toBeInstanceOf(ZonaKolam::class);
});

test('sampling stores a generated average weight', function () {
    $kolam = Kolam::create([
        'kode' => 'C08',
        'nama' => 'Kolam C08',
        'panjang_m' => 4,
        'lebar_m' => 2,
        'tinggi_air_m' => 1,
        'jumlah_ikan_awal' => 500,
        'bobot_awal_rata_g' => 2,
        'panjang_awal_rata_cm' => 4,
        'koef_a' => 0.01,
        'waktu_mulai_sandbox' => now(),
        'waktu_sandbox_sekarang' => now(),
    ]);

    $sampling = SamplingPertumbuhan::create([
        'kolam_id' => $kolam->id,
        'tanggal' => now()->toDateString(),
        'jumlah_sampel' => 10,
        'bobot_total_sampel_g' => 250,
        'panjang_rata_cm' => 12.5,
    ]);

    expect($sampling->refresh()->bobot_rata_g)->toBe(25.0);
});
