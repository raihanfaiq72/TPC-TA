<?php

use App\Models\Kolam;
use Database\Seeders\SandboxSeeder;

test('simulation can advance a full virtual day and run feeding daily process alerts', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/simulasi/tick", [
        'menit' => 1440,
    ])
        ->assertOk()
        ->assertJsonPath('proses_harian', true)
        ->assertJsonPath('tick_menit', 10)
        ->assertJsonPath('selesai', true);

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
