<?php

use App\Models\Kolam;
use App\Models\SamplingPertumbuhan;
use Database\Seeders\SandboxSeeder;

test('api can list, show, update, and delete sampling records', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $this->postJson("/api/kolam/{$kolam->id}/sampling", [
        'jumlah_sampel' => 5,
        'jumlah_mati' => 2,
        'bobot_total_sampel_g' => 440.5,
        'panjang_rata_cm' => 17.9,
    ])->assertOk();

    $sampling = SamplingPertumbuhan::query()
        ->where('kolam_id', $kolam->id)
        ->orderByDesc('id')
        ->firstOrFail();

    expect($sampling->jumlah_mati)->toBe(2);

    $this->getJson("/api/kolam/{$kolam->id}/sampling")
        ->assertOk()
        ->assertJsonPath('0.id', $sampling->id)
        ->assertJsonPath('0.jumlah_mati', 2);

    $this->getJson("/api/kolam/{$kolam->id}/sampling/{$sampling->id}")
        ->assertOk()
        ->assertJsonPath('id', $sampling->id)
        ->assertJsonPath('jumlah_sampel', 5)
        ->assertJsonPath('jumlah_mati', 2);

    $this->putJson("/api/kolam/{$kolam->id}/sampling/{$sampling->id}", [
        'panjang_rata_cm' => 18.1,
        'jumlah_mati' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Data sampling berhasil diperbarui');

    expect($sampling->refresh()->panjang_rata_cm)->toBe(18.1)
        ->and($sampling->jumlah_mati)->toBe(3);

    $this->deleteJson("/api/kolam/{$kolam->id}/sampling/{$sampling->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Data sampling berhasil dihapus');

    $this->getJson("/api/kolam/{$kolam->id}/sampling/{$sampling->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Data sampling tidak ditemukan');
});
