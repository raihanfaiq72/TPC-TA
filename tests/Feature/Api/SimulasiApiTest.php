<?php

use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\SiklusKolam;
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

test('simulation stops and finishes the pond when live fish reach the harvest target', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();
    $bobotRata = (float) Ikan::query()
        ->where('kolam_id', $kolam->id)
        ->where('status', 'hidup')
        ->avg('bobot_g');

    $kolam->update([
        'bobot_target_panen_g' => max(1.0, round($bobotRata - 0.1, 2)),
    ]);

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
        'menit' => 1440,
    ])
        ->assertOk()
        ->assertJsonPath('target_tercapai', true)
        ->assertJsonPath('status_kolam', 'selesai')
        ->assertJsonPath('tick_dijalankan', 0)
        ->assertJsonPath('menit_dijalankan', 0)
        ->assertJsonPath('selesai', true);

    expect($kolam->fresh()->status)->toBe('selesai');

    $this->getJson("/api/kolam/{$kolam->id}/simulasi")
        ->assertOk()
        ->assertJsonPath('kolam.status', 'selesai')
        ->assertJsonPath('ikan.target_tercapai', true)
        ->assertJsonStructure([
            'ikan' => ['bobot_rata_hidup_g', 'target_bobot_g', 'target_tercapai'],
        ]);
});

test('api can update the feeding schedule used by auto pilot', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->putJson("/api/kolam/{$kolam->id}/pakan/jadwal", [
        'jadwal' => ['06:30', '12:30', '18:30'],
    ])
        ->assertOk()
        ->assertJsonPath('jadwal_pakan.0.jam', '06:30:00')
        ->assertJsonPath('jadwal_pakan.1.jam', '12:30:00')
        ->assertJsonPath('jadwal_pakan.2.jam', '18:30:00');

    expect(DB::table('jadwal_pakans')
        ->where('kolam_id', $kolam->id)
        ->where('aktif', true)
        ->orderBy('jam')
        ->pluck('jam')
        ->all())
        ->toBe(['06:30:00', '12:30:00', '18:30:00']);
});

test('auto pilot state can be started and force stopped', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/auto-pilot/start")
        ->assertOk()
        ->assertJsonPath('auto_pilot_aktif', true);

    expect($kolam->fresh()->auto_pilot_aktif)->toBeTrue();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/auto-pilot/stop")
        ->assertOk()
        ->assertJsonPath('auto_pilot_aktif', false);

    expect($kolam->fresh()->auto_pilot_aktif)->toBeFalse();
});

test('auto pilot creates and advances pond cycles with different stock and target', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/auto-pilot/start", [
        'siklus' => [
            ['jumlah_tebar' => 120, 'target_bobot_g' => 10],
            ['jumlah_tebar' => 80, 'target_bobot_g' => 25],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('auto_pilot_aktif', true)
        ->assertJsonPath('bobot_target_panen_g', 10);

    $kolam->refresh();

    expect($kolam->jumlah_ikan_awal)->toBe(120)
        ->and($kolam->bobot_target_panen_g)->toBe(10.0)
        ->and(SiklusKolam::query()->where('kolam_id', $kolam->id)->where('status', 'aktif')->value('urutan'))
        ->toBe(1);

    $this->postJson("/api/kolam/{$kolam->id}/siklus/next")
        ->assertOk()
        ->assertJsonPath('selesai', false)
        ->assertJsonPath('jumlah_tebar', 80)
        ->assertJsonPath('bobot_target_panen_g', 25);

    $kolam->refresh();

    expect($kolam->jumlah_ikan_awal)->toBe(80)
        ->and($kolam->bobot_target_panen_g)->toBe(25.0)
        ->and(SiklusKolam::query()->where('kolam_id', $kolam->id)->where('status', 'selesai')->count())->toBe(1)
        ->and(SiklusKolam::query()->where('kolam_id', $kolam->id)->where('status', 'aktif')->value('urutan'))->toBe(2);
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
