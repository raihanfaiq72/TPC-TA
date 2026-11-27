<?php

use App\Models\Kolam;
use Database\Seeders\SandboxSeeder;

test('export ringkas and lengkap provide training datasets', function () {
    $this->seed(SandboxSeeder::class);

    $kolam = Kolam::query()->where('kode', 'C07')->firstOrFail();

    $ringkas = $this->getJson("/api/kolam/{$kolam->id}/export/ringkas?format=json")
        ->assertOk()
        ->json();

    expect($ringkas)->toBeArray()->not->toBeEmpty();

    $this->assertArrayHasKey('tanggal', $ringkas[0]);
    $this->assertArrayHasKey('suhu_atas', $ringkas[0]);
    $this->assertArrayHasKey('sampling_bobot_rata_g', $ringkas[0]);

    $ringkasCsv = $this->get("/api/kolam/{$kolam->id}/export/ringkas?format=csv")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($ringkasCsv)->toStartWith('tanggal,kolam_kode,kolam_nama');

    $lengkap = $this->get("/api/kolam/{$kolam->id}/export/lengkap?format=json")
        ->assertOk()
        ->assertHeader('content-type', 'application/json; charset=UTF-8')
        ->streamedContent();
    $payload = json_decode($lengkap, true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toHaveKeys(['meta', 'sensor', 'populasi', 'sampling'])
        ->and($payload['meta']['versi'])->toBe('lengkap')
        ->and($payload['sensor'])->toBeArray()
        ->and($payload['populasi'])->toBeArray();

    $lengkapCsv = $this->get("/api/kolam/{$kolam->id}/export/lengkap?format=csv")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($lengkapCsv)->toStartWith('waktu_ukur,perangkat,zona,parameter,nilai');

    $this->getJson("/api/kolam/{$kolam->id}/export/ringkas?format=excel")
        ->assertStatus(422);
});

test('export returns not found for an unknown pond', function () {
    $this->getJson('/api/kolam/999999/export/ringkas?format=csv')
        ->assertNotFound()
        ->assertJsonPath('message', 'Kolam tidak ditemukan.');
});
