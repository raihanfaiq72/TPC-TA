<?php

use App\Models\Ikan;
use App\Models\IkanLogHarian;
use App\Models\Kolam;
use Database\Seeders\SandboxSeeder;

function konfigurasiIkanUntukTest(): array
{
    return [
        'sandbox.tick_menit' => 10,
        'sandbox.ikan' => [
            'ambang_do_bawah' => 3.0,
            'bonus_naik_do_bawah_rendah' => 0.2,
            'ambang_suhu_atas' => 31.0,
            'penalti_naik_suhu_atas' => 0.2,
            'bonus_naik_dekat_pakan' => 0.3,
            'bonus_naik_lapar' => 0.4,
            'peluang_pindah_tick' => 0.5,
            'jam_lambung_kosong' => 6.0,
            'kebutuhan_pakan_harian' => 0.05,
            'sgr_ref' => 2.0,
            'bobot_ref_g' => 1.0,
            'sgr_eksponen' => 0.2,
            'pemulihan_stres_per_hari' => 5.0,
            'rasio_ukuran_kanibal' => 0.5,
            'ambang_stres_mati' => 70.0,
            'peluang_mati_dasar' => 0.01,
            'peluang_mati_stres_maks' => 0.2,
            'peluang_kanibal' => 0.2,
        ],
        'sandbox.lingkungan' => [
            'amonia_dari_bangkai' => 0.01,
        ],
    ];
}

test('api can show fish summary for a pond', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->getJson("/api/kolam/{$kolam->id}/ikan")
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonPath('ringkasan.jumlah_total', 12)
        ->assertJsonPath('ringkasan.jumlah_hidup', 11)
        ->assertJsonPath('ringkasan.jumlah_mati', 1)
        ->assertJsonCount(12, 'ikan')
        ->assertJsonStructure([
            'kolam' => ['id', 'kode', 'nama', 'volume_air_m3', 'waktu_sandbox_sekarang'],
            'ringkasan' => [
                'jumlah_total',
                'jumlah_hidup',
                'jumlah_mati',
                'biomassa_hidup_g',
                'bobot_rata_hidup_g',
                'stres_rata_hidup',
            ],
            'ikan' => [
                '*' => [
                    'id',
                    'kode',
                    'bobot_g',
                    'panjang_cm',
                    'stres',
                    'status',
                    'zona',
                ],
            ],
        ]);
});

test('api can run fish movement and daily process', function () {
    config(konfigurasiIkanUntukTest());

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/ikan/gerak", [
        'waktu' => '2026-11-08 14:00:00',
    ])
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonPath('ringkasan.jumlah_hidup', 11)
        ->assertJsonCount(12, 'ikan');

    $ikanHidup = Ikan::query()
        ->where('kolam_id', $kolam->id)
        ->where('status', 'hidup')
        ->get();

    expect($ikanHidup)->toHaveCount(11)
        ->and($ikanHidup->sum(fn (Ikan $ikan): int => $ikan->hari_tick_atas + $ikan->hari_tick_bawah))->toBeGreaterThan(0);

    $this->postJson("/api/kolam/{$kolam->id}/ikan/proses-harian", [
        'tanggal' => '2026-11-08',
    ])
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonPath('ringkasan.jumlah_total', 12)
        ->assertJsonStructure([
            'ringkasan' => [
                'jumlah_total',
                'jumlah_hidup',
                'jumlah_mati',
                'biomassa_hidup_g',
                'bobot_rata_hidup_g',
                'stres_rata_hidup',
            ],
        ]);

    expect(IkanLogHarian::query()->whereDate('tanggal_sandbox', '2026-11-08')->count())->toBe(11)
        ->and(Ikan::query()->where('kolam_id', $kolam->id)->sum('hari_tick_atas'))->toBe(0)
        ->and(Ikan::query()->where('kolam_id', $kolam->id)->sum('hari_tick_bawah'))->toBe(0)
        ->and(Ikan::query()->where('kolam_id', $kolam->id)->sum('hari_pakan_g'))->toBe(0);
});

test('api fish movement returns service unavailable when ikan config is missing', function () {
    config(['sandbox.ikan' => null]);

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/ikan/gerak", [
        'waktu' => '2026-11-08 14:00:00',
    ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Konfigurasi sandbox.ikan belum tersedia.');
});

test('api fish daily process returns service unavailable when ikan config is missing', function () {
    config(['sandbox.ikan' => null]);

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/ikan/proses-harian", [
        'tanggal' => '2026-11-08',
    ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Konfigurasi sandbox.ikan belum tersedia.');
});

test('api fish returns not found for an unknown pond', function () {
    $this->getJson('/api/kolam/999999/ikan')
        ->assertNotFound()
        ->assertJsonPath('message', 'Kolam tidak ditemukan.');
});
