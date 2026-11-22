<?php

namespace Database\Seeders;

use App\Models\JenisPerangkat;
use Illuminate\Database\Seeder;

class JenisPerangkatSeeder extends Seeder
{
    /**
     * Seed the master list of device types.
     */
    public function run(): void
    {
        $rows = [
            [
                'kode' => 'suhu',
                'nama' => 'Sensor Suhu',
                'parameter' => 'suhu',
                'satuan' => '°C',
                'nilai_min_fisik' => 0,
                'nilai_maks_fisik' => 50,
                'interval_default_menit' => 10,
                'tingkat' => 'zona',
                'noise_sd' => 0.15,
                'peluang_error' => 0.005,
            ],
            [
                'kode' => 'ph',
                'nama' => 'Sensor pH',
                'parameter' => 'ph',
                'satuan' => 'pH',
                'nilai_min_fisik' => 0,
                'nilai_maks_fisik' => 14,
                'interval_default_menit' => 15,
                'tingkat' => 'zona',
                'noise_sd' => 0.05,
                'peluang_error' => 0.005,
            ],
            [
                'kode' => 'do',
                'nama' => 'Sensor DO',
                'parameter' => 'do',
                'satuan' => 'mg/L',
                'nilai_min_fisik' => 0,
                'nilai_maks_fisik' => 20,
                'interval_default_menit' => 15,
                'tingkat' => 'zona',
                'noise_sd' => 0.10,
                'peluang_error' => 0.005,
            ],
            [
                'kode' => 'amonia',
                'nama' => 'Sensor Amonia',
                'parameter' => 'amonia',
                'satuan' => 'ppm',
                'nilai_min_fisik' => 0,
                'nilai_maks_fisik' => 10,
                'interval_default_menit' => 60,
                'tingkat' => 'zona',
                'noise_sd' => 0.03,
                'peluang_error' => 0.010,
            ],
            [
                'kode' => 'feeder',
                'nama' => 'Feeder Otomatis',
                'parameter' => 'pakan',
                'satuan' => 'g',
                'nilai_min_fisik' => null,
                'nilai_maks_fisik' => null,
                'interval_default_menit' => 0,
                'tingkat' => 'kolam',
                'noise_sd' => 0,
                'peluang_error' => 0,
            ],
            [
                'kode' => 'level',
                'nama' => 'Sensor Level Air',
                'parameter' => 'level',
                'satuan' => 'cm',
                'nilai_min_fisik' => 0,
                'nilai_maks_fisik' => 100,
                'interval_default_menit' => 30,
                'tingkat' => 'kolam',
                'noise_sd' => 0.20,
                'peluang_error' => 0.002,
            ],
        ];

        foreach ($rows as $row) {
            JenisPerangkat::query()->updateOrCreate(
                ['kode' => $row['kode']],
                $row,
            );
        }
    }
}
