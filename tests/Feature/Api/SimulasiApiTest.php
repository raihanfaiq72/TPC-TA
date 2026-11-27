<?php

use App\Models\Ikan;
use App\Models\Kolam;
use Database\Seeders\SandboxSeeder;
use Illuminate\Support\Facades\DB;

test('simulation can advance a full virtual day and run feeding daily process alerts', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $sensorSebelum = DB::table('pembacaan_sensors')->where('kolam_id', $kolam->id)->count();
    $pakanSebelum = DB::table('log_pakans')->where('kolam_id', $kolam->id)->count();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
        'menit' => 1440,
    ])
        ->assertOk()
        ->assertJsonPath('proses_harian', true)
        ->assertJsonPath('tick_menit', 10)
        ->assertJsonPath('selesai', true);

    expect(DB::table('pembacaan_sensors')->where('kolam_id', $kolam->id)->count())->toBeGreaterThan($sensorSebelum)
        ->and(DB::table('log_pakans')->where('kolam_id', $kolam->id)->count())->toBeGreaterThan($pakanSebelum);

    $kolam->refresh();

    expect($kolam->waktu_sandbox_sekarang->toDateTimeString())->toBe('2026-11-09 14:00:00');

    $this->getJson("/api/kolam/{$kolam->id}/simulasi")
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonStructure([
            'kolam',
            'ikan' => ['jumlah_awal', 'jumlah_hidup', 'jumlah_mati', 'biomassa_hidup_g'],
            'lingkungan',
        ]);
});

test('simulation persists daily fish logs for a new virtual date', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $ikanIds = Ikan::query()->where('kolam_id', $kolam->id)->pluck('id');
    $logTanggalBaruSebelum = DB::table('ikan_log_harians')
        ->whereIn('ikan_id', $ikanIds)
        ->whereDate('tanggal_sandbox', '2026-11-09')
        ->count();

    foreach ([1, 2] as $day) {
        $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
            'menit' => 1440,
        ])
            ->assertOk()
            ->assertJsonPath('selesai', true);

        $logTanggalBaru = DB::table('ikan_log_harians')
            ->whereIn('ikan_id', $ikanIds)
            ->whereDate('tanggal_sandbox', '2026-11-09')
            ->count();

        if ($day === 1) {
            expect($logTanggalBaru)->toBe($logTanggalBaruSebelum);
        } else {
            expect($logTanggalBaru)->toBeGreaterThan($logTanggalBaruSebelum);
        }
    }

    expect($kolam->fresh()->waktu_sandbox_sekarang->toDateTimeString())->toBe('2026-11-10 14:00:00');
});

test('feeding log is inserted when a schedule falls inside the tick interval', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $kolam->update(['waktu_sandbox_sekarang' => '2026-11-08 15:55:00']);
    $pakanSebelum = DB::table('log_pakans')->where('kolam_id', $kolam->id)->count();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
        'menit' => 20,
    ])
        ->assertOk()
        ->assertJsonPath('pakan_kejadian', 1)
        ->assertJsonPath('pakan_events.0.waktu', '2026-11-08 16:05:00');

    expect(DB::table('log_pakans')->where('kolam_id', $kolam->id)->count())
        ->toBeGreaterThan($pakanSebelum);
});

test('simulation returns partial progress when the per-request tick budget is reached', function () {
    config(['sandbox.maks_tick_per_eksekusi' => 1]);

    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $sebelum = $kolam->waktu_sandbox_sekarang->copy();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
        'menit' => 1440,
    ])
        ->assertOk()
        ->assertJsonPath('selesai', false)
        ->assertJsonPath('tick_dijalankan', 1)
        ->assertJsonPath('menit_dijalankan', 10);

    expect($kolam->fresh()->waktu_sandbox_sekarang->toDateTimeString())
        ->toBe($sebelum->copy()->addMinutes(10)->toDateTimeString());
});
