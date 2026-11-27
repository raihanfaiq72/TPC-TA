<?php

use App\Models\KeadaanZona;
use App\Models\Kolam;
use Database\Seeders\SandboxSeeder;

test('api can show lingkungan state for a pond', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->getJson("/api/kolam/{$kolam->id}/lingkungan")
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonCount(2, 'zona')
        ->assertJsonCount(3, 'skenario')
        ->assertJsonStructure([
            'kolam' => ['id', 'kode', 'nama', 'volume_air_m3', 'waktu_sandbox_sekarang'],
            'zona' => [
                '*' => [
                    'id',
                    'nama',
                    'kedalaman_cm',
                    'porsi_volume',
                    'keadaan' => ['suhu_asli', 'ph_asli', 'do_asli', 'amonia_asli', 'sisa_pakan_g', 'diperbarui_pada'],
                    'profil',
                ],
            ],
            'skenario',
        ]);
});

test('api can add ammonia to all zones of a pond', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $sebelum = KeadaanZona::query()->where('kolam_id', $kolam->id)->orderBy('zona_id')->pluck('amonia_asli', 'zona_id');

    $this->postJson("/api/kolam/{$kolam->id}/lingkungan/amonia", [
        'gram_tan' => 9,
    ])
        ->assertOk()
        ->assertJsonCount(2, 'zona');

    $sesudah = KeadaanZona::query()->where('kolam_id', $kolam->id)->orderBy('zona_id')->pluck('amonia_asli', 'zona_id');

    foreach ($sebelum as $zonaId => $nilai) {
        expect($sesudah[$zonaId])->toBeGreaterThan($nilai);
    }
});

test('api can add leftover feed to the top zone', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $atas = KeadaanZona::query()
        ->join('zona_kolams', 'zona_kolams.id', '=', 'keadaan_zonas.zona_id')
        ->where('keadaan_zonas.kolam_id', $kolam->id)
        ->where('zona_kolams.nama', 'atas')
        ->select('keadaan_zonas.*')
        ->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/lingkungan/sisa-pakan", [
        'gram' => 100,
    ])
        ->assertOk()
        ->assertJsonCount(2, 'zona');

    expect($atas->refresh()->sisa_pakan_g)->toBeGreaterThan(100);
});

test('api can run one lingkungan tick when sandbox config is available', function () {
    config([
        'sandbox.tick_menit' => 10,
        'sandbox.lingkungan' => [
            'puncak_suhu_jam' => 14,
            'suhu_relaksasi_per_tick' => 0.25,
            'do_jenuh_ref' => 8.5,
            'do_jenuh_per_derajat' => 0.2,
            'rea_atas_per_hari' => 2.0,
            'rea_bawah_per_hari' => 1.0,
            'resp_koef_suhu' => 0.05,
            'resp_per_kg_m3_jam' => 0.01,
            'busuk_do_per_g_m3_jam' => 0.001,
            'sisa_pakan_peluruhan_tick' => 0.1,
            'amonia_hilang_per_hari' => 0.2,
            'amonia_dari_sisa_busuk' => 0.01,
            'sisa_pakan_mengendap_tick' => 0.05,
            'campur_antar_zona' => 0.1,
            'ph_turun_per_ppm_amonia' => 0.33,
            'ph_relaksasi_per_tick' => 0.25,
        ],
    ]);

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/lingkungan/tick", [
        'waktu' => '2026-11-08 14:00:00',
    ])
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonCount(2, 'zona')
        ->assertJsonPath('zona.0.keadaan.diperbarui_pada', '2026-11-08T14:00:00.000000Z');
});

test('api tick returns service unavailable when sandbox config is missing', function () {
    config(['sandbox.lingkungan' => null]);

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/lingkungan/tick", [
        'waktu' => '2026-11-08 14:00:00',
    ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Konfigurasi sandbox.lingkungan belum tersedia.');
});

test('api lingkungan returns not found for an unknown pond', function () {
    $this->getJson('/api/kolam/999999/lingkungan')
        ->assertNotFound()
        ->assertJsonPath('message', 'Kolam tidak ditemukan.');
});
