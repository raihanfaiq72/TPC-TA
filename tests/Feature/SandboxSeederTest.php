<?php

use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SandboxSeeder;
use Illuminate\Support\Facades\DB;

test('sandbox seeder fills every table with an example', function () {
    $this->seed(SandboxSeeder::class);

    $harapan = [
        'kolams' => 1,
        'zona_kolams' => 2,
        'jenis_perangkats' => 6,
        'perangkats' => 10,
        'pembacaan_sensors' => 27,
        'jenis_pakans' => 1,
        'jadwal_pakans' => 3,
        'log_pakans' => 6,
        'keadaan_zonas' => 2,
        'ikans' => 12,
        'ikan_log_harians' => 33,
        'profil_lingkungans' => 8,
        'skenario_simulasis' => 3,
        'sampling_pertumbuhans' => 4,
        'aturan_ambangs' => 16,
        'alerts' => 3,
        'rekomendasi_panens' => 3,
    ];

    foreach ($harapan as $tabel => $jumlah) {
        expect(DB::table($tabel)->count())->toBe($jumlah, "Tabel {$tabel}");
    }

    $rataSampling = DB::table('sampling_pertumbuhans')
        ->orderBy('tanggal')
        ->pluck('bobot_rata_g')
        ->map(fn ($nilai) => (float) $nilai)
        ->all();

    expect($rataSampling)->toEqual([35.0, 53.0, 70.0, 88.4]);
});

test('sandbox seeder is idempotent when run twice', function () {
    $this->seed(SandboxSeeder::class);
    $this->seed(SandboxSeeder::class);

    expect(Kolam::count())->toBe(1)
        ->and(Ikan::count())->toBe(12)
        ->and(DB::table('perangkats')->count())->toBe(10)
        ->and(DB::table('pembacaan_sensors')->count())->toBe(27)
        ->and(DB::table('ikan_log_harians')->count())->toBe(33);
});

test('database seeder can run twice without duplicate test user', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and(Kolam::count())->toBe(1)
        ->and(Ikan::count())->toBe(12);
});
