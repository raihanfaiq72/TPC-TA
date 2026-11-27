<?php

use App\Models\Kolam;
use App\Models\PembacaanSensor;
use App\Models\Perangkat;
use Database\Seeders\SandboxSeeder;

test('api can enable and disable a sensor', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $device = Perangkat::query()
        ->where('kolam_id', $kolam->id)
        ->where('status', 'aktif')
        ->whereHas('jenisPerangkat', fn ($query) => $query->where('kode', 'suhu'))
        ->firstOrFail();
    $before = PembacaanSensor::query()->where('perangkat_id', $device->id)->count();

    $this->patchJson("/api/kolam/{$kolam->id}/sensor/{$device->id}", [
        'status' => 'mati',
    ])
        ->assertOk()
        ->assertJsonPath('perangkat.status', 'mati');

    $this->postJson("/api/kolam/{$kolam->id}/sensor/baca", [
        'waktu' => '2026-11-08 15:00:00',
    ])->assertOk();

    expect(PembacaanSensor::query()->where('perangkat_id', $device->id)->count())->toBe($before);

    $this->patchJson("/api/kolam/{$kolam->id}/sensor/{$device->id}", [
        'status' => 'aktif',
    ])
        ->assertOk()
        ->assertJsonPath('perangkat.status', 'aktif');
});

test('api can force-read all active sensors for live chart heartbeat', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $activeSensors = Perangkat::query()
        ->where('kolam_id', $kolam->id)
        ->where('status', 'aktif')
        ->whereHas('jenisPerangkat', fn ($query) => $query->where('kode', '!=', 'feeder'))
        ->count();
    $sebelum = PembacaanSensor::query()->where('kolam_id', $kolam->id)->count();

    $this->postJson("/api/kolam/{$kolam->id}/sensor/baca", [
        'waktu' => '2026-11-08 14:10:00',
        'paksa' => true,
    ])
        ->assertOk()
        ->assertJsonPath('paksa', true)
        ->assertJsonPath('jumlah_dibaca', $activeSensors);

    expect(PembacaanSensor::query()->where('kolam_id', $kolam->id)->count())
        ->toBeGreaterThan($sebelum);
});

test('api can read sensors and show latest and history readings', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $sebelum = PembacaanSensor::query()->where('kolam_id', $kolam->id)->count();

    $response = $this->postJson("/api/kolam/{$kolam->id}/sensor/baca", [
        'waktu' => '2026-11-08 14:10:00',
    ])->assertOk()
        ->assertJsonPath('kolam_id', $kolam->id)
        ->assertJsonStructure(['kolam_id', 'waktu', 'jumlah_dibaca']);

    expect($response->json('jumlah_dibaca'))->toBeGreaterThan(0)
        ->and(PembacaanSensor::query()->where('kolam_id', $kolam->id)->count())->toBeGreaterThan($sebelum);

    $this->getJson("/api/kolam/{$kolam->id}/sensor/terbaru")
        ->assertOk()
        ->assertJsonStructure([
            '*' => [
                'perangkat',
                'zona',
                'parameter',
                'nilai',
                'satuan',
                'waktu_ukur',
                'kualitas_data',
            ],
        ]);

    $this->getJson("/api/kolam/{$kolam->id}/sensor/riwayat?parameter=do&zona=atas&dari=2026-11-08&sampai=2026-11-09")
        ->assertOk()
        ->assertJsonPath('0.parameter', 'do')
        ->assertJsonStructure([
            '*' => ['waktu_ukur', 'zona', 'parameter', 'nilai', 'kualitas_data'],
        ]);

    $this->getJson("/api/kolam/{$kolam->id}/sensor/grafik?range=1d")
        ->assertOk()
        ->assertJsonStructure([
            '*' => ['waktu', 'zona', 'parameter', 'nilai'],
        ]);
});
