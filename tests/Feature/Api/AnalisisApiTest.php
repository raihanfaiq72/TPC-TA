<?php

use App\Http\Controllers\Api\AnalisisController;
use App\Models\Alert;
use App\Models\Kolam;
use App\Models\SamplingPertumbuhan;
use Database\Seeders\SandboxSeeder;

test('api can list alerts, latest recommendation, and validation summary', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->getJson("/api/kolam/{$kolam->id}/alert")
        ->assertOk()
        ->assertJsonCount(3);

    $this->getJson("/api/kolam/{$kolam->id}/rekomendasi")
        ->assertOk()
        ->assertJsonPath('kolam_id', $kolam->id)
        ->assertJsonPath('status', 'hampir siap');

    $this->getJson("/api/kolam/{$kolam->id}/validasi")
        ->assertOk()
        ->assertJsonPath('ringkasan.awal', 12)
        ->assertJsonPath('ringkasan.hidup', 11)
        ->assertJsonPath('penyebab_mati.0.penyebab_mati', 'stres_suhu');
});

test('analisis creates alerts once and avoids duplicates in six virtual hours', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $analisis = app(AnalisisController::class);

    $pertama = $analisis->evaluasiAlert($kolam->id, '2026-11-08 10:00:00');

    expect($pertama)->toBeGreaterThan(0);

    $kedua = $analisis->evaluasiAlert($kolam->id, '2026-11-08 10:00:00');

    expect($kedua)->toBe(0)
        ->and(Alert::query()->where('kolam_id', $kolam->id)->count())->toBe(3 + $pertama);
});

test('api sampling stores the sample and returns a harvest recommendation', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/sampling", [
        'jumlah_sampel' => 5,
        'bobot_total_sampel_g' => 440.5,
        'panjang_rata_cm' => 17.9,
    ])
        ->assertOk()
        ->assertJsonPath('tanggal', '2026-11-08')
        ->assertJsonPath('rekomendasi.status', 'hampir siap');

    expect(SamplingPertumbuhan::query()
        ->where('kolam_id', $kolam->id)
        ->whereDate('tanggal', '2026-11-08')
        ->count())->toBeGreaterThan(0);
});
