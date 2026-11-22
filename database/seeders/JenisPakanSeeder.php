<?php

namespace Database\Seeders;

use App\Models\JenisPakan;
use Illuminate\Database\Seeder;

class JenisPakanSeeder extends Seeder
{
    /**
     * Seed the default feed brand.
     */
    public function run(): void
    {
        JenisPakan::query()->updateOrCreate(
            ['merek' => 'Provit 781-3'],
            [
                'bentuk' => 'apung',
                'protein_persen' => 31,
                'lemak_persen' => 5,
            ],
        );
    }
}
