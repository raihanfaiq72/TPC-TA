<?php

use App\Models\Ikan;
use App\Models\Kolam;
use Database\Seeders\JenisPakanSeeder;
use Database\Seeders\JenisPerangkatSeeder;
use Database\Seeders\SandboxSeeder;
use Illuminate\Support\Facades\DB;

test('api ping responds with json', function () {
    $this->getJson('/api/ping')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});

test('api can list and show sandbox ponds', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->getJson('/api/kolam')
        ->assertOk()
        ->assertJsonPath('0.kode', 'C07')
        ->assertJsonPath('0.jumlah_ikan_hidup', 11)
        ->assertJsonPath('0.jumlah_zona', 2);

    $this->getJson("/api/kolam/{$kolam->id}")
        ->assertOk()
        ->assertJsonPath('kolam.id', $kolam->id)
        ->assertJsonCount(2, 'zona')
        ->assertJsonCount(10, 'perangkat')
        ->assertJsonCount(3, 'jadwal_pakan')
        ->assertJsonPath('perangkat.0.jenis_perangkat', 'Sensor Suhu')
        ->assertJsonPath('jadwal_pakan.0.nama_pakan', 'Provit 781-3');
});

test('api can provision a sandbox pond', function () {
    $this->seed([
        JenisPerangkatSeeder::class,
        JenisPakanSeeder::class,
    ]);

    $response = $this->postJson('/api/sandbox/kolam', [
        'kode' => 'C99',
        'nama' => 'Kolam API',
        'panjang_m' => 2,
        'lebar_m' => 1,
        'tinggi_air_m' => 1,
        'jumlah_ikan_awal' => 10,
        'bobot_awal_rata_g' => 1.2,
        'bobot_awal_sd_g' => 0.1,
        'panjang_awal_rata_cm' => 3.5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('kode', 'C99')
        ->assertJsonPath('jumlah_ikan_awal', 10)
        ->assertJsonPath('volume_air_m3', 2)
        ->assertJsonPath('padat_tebar_per_m3', 5);

    $kolamId = $response->json('id');

    expect(DB::table('zona_kolams')->where('kolam_id', $kolamId)->count())->toBe(2)
        ->and(DB::table('perangkats')->where('kolam_id', $kolamId)->count())->toBe(10)
        ->and(DB::table('ikans')->where('kolam_id', $kolamId)->count())->toBe(10)
        ->and(Ikan::query()->where('kolam_id', $kolamId)->pluck('bobot_g')->unique()->count())->toBe(1)
        ->and(DB::table('jadwal_pakans')->where('kolam_id', $kolamId)->count())->toBe(3)
        ->and(DB::table('profil_lingkungans')->where('kolam_id', $kolamId)->count())->toBe(8)
        ->and(DB::table('keadaan_zonas')->where('kolam_id', $kolamId)->count())->toBe(2);
});

test('api can update pond dimensions and recalculate volume density', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->putJson("/api/kolam/{$kolam->id}", [
        'nama' => 'Kolam C07 diperbarui',
        'panjang_m' => 4,
        'lebar_m' => 2,
        'tinggi_air_m' => 1.5,
    ])
        ->assertOk()
        ->assertJsonPath('kolam.nama', 'Kolam C07 diperbarui')
        ->assertJsonPath('kolam.volume_air_m3', 12)
        ->assertJsonPath('kolam.padat_tebar_per_m3', round($kolam->jumlah_ikan_awal / 12, 2));

    expect((float) $kolam->fresh()->volume_air_m3)->toBe(12.0);
});

test('api can delete a pond with its related data', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->deleteJson("/api/kolam/{$kolam->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Kolam berhasil dihapus');

    expect(Kolam::query()->find($kolam->id))->toBeNull()
        ->and(DB::table('zona_kolams')->where('kolam_id', $kolam->id)->count())->toBe(0)
        ->and(DB::table('ikans')->where('kolam_id', $kolam->id)->count())->toBe(0)
        ->and(DB::table('perangkats')->where('kolam_id', $kolam->id)->count())->toBe(0);
});
