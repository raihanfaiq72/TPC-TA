<?php

namespace Database\Seeders;

use App\Models\AturanAmbang;
use Illuminate\Database\Seeder;

class AturanAmbangSeeder extends Seeder
{
    /**
     * Seed the reference threshold bands (half-open: min <= nilai < max).
     */
    public function run(): void
    {
        $rows = [
            [
                'parameter' => 'do',
                'zona' => null,
                'batas_min' => null,
                'batas_maks' => 3,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Haryasakti & Wahyudi 2023 hlm.157 (DO >3 mg/L); Patriono dkk. 2021 hlm.86 (DO <3 belum memenuhi syarat)',
            ],
            [
                'parameter' => 'do',
                'zona' => null,
                'batas_min' => 3,
                'batas_maks' => 5,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.86 (DO baik 5-7; <4 nafsu makan turun)',
            ],
            [
                'parameter' => 'do',
                'zona' => null,
                'batas_min' => 5,
                'batas_maks' => null,
                'tingkat' => 'aman',
                'sumber_rujukan' => 'Saselah & Indriani hlm.28 (DO ideal >=5 mg/L)',
            ],
            [
                'parameter' => 'suhu',
                'zona' => null,
                'batas_min' => null,
                'batas_maks' => 20,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Saselah & Indriani hlm.28 (suhu <20 C menghambat pertumbuhan)',
            ],
            [
                'parameter' => 'suhu',
                'zona' => null,
                'batas_min' => 20,
                'batas_maks' => 26,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.87 (26-31 C untuk lele)',
            ],
            [
                'parameter' => 'suhu',
                'zona' => null,
                'batas_min' => 26,
                'batas_maks' => 31,
                'tingkat' => 'aman',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.87 (Augusta 2016: 26-31 C)',
            ],
            [
                'parameter' => 'suhu',
                'zona' => null,
                'batas_min' => 31,
                'batas_maks' => 35,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Saselah & Indriani hlm.28 (suhu >35 C stres)',
            ],
            [
                'parameter' => 'suhu',
                'zona' => null,
                'batas_min' => 35,
                'batas_maks' => null,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Saselah & Indriani hlm.28 (suhu >35 C stres)',
            ],
            [
                'parameter' => 'ph',
                'zona' => null,
                'batas_min' => null,
                'batas_maks' => 5,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.86 (pH <5 hambat; Saselah hlm.28 lendir insang menggumpal)',
            ],
            [
                'parameter' => 'ph',
                'zona' => null,
                'batas_min' => 5,
                'batas_maks' => 6.5,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.86 (optimal 6,5-9,0)',
            ],
            [
                'parameter' => 'ph',
                'zona' => null,
                'batas_min' => 6.5,
                'batas_maks' => 8.5,
                'tingkat' => 'aman',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.86-87; Saselah & Indriani hlm.28 (6-8)',
            ],
            [
                'parameter' => 'ph',
                'zona' => null,
                'batas_min' => 8.5,
                'batas_maks' => 9,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Patriono dkk. 2021 hlm.86 (batas atas 9,0)',
            ],
            [
                'parameter' => 'ph',
                'zona' => null,
                'batas_min' => 9,
                'batas_maks' => null,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Saselah & Indriani hlm.28 (pH >8 menurunkan nafsu makan)',
            ],
            [
                'parameter' => 'amonia',
                'zona' => null,
                'batas_min' => null,
                'batas_maks' => 0.5,
                'tingkat' => 'aman',
                'sumber_rujukan' => 'Haryasakti & Wahyudi 2023 hlm.157 (PP baku mutu <=0,5 mg/L)',
            ],
            [
                'parameter' => 'amonia',
                'zona' => null,
                'batas_min' => 0.5,
                'batas_maks' => 1.5,
                'tingkat' => 'waspada',
                'sumber_rujukan' => 'Haryasakti & Wahyudi 2023 hlm.157 (di atas 0,5 s.d. batas toksik 1,5)',
            ],
            [
                'parameter' => 'amonia',
                'zona' => null,
                'batas_min' => 1.5,
                'batas_maks' => null,
                'tingkat' => 'kritis',
                'sumber_rujukan' => 'Haryasakti & Wahyudi 2023 hlm.157 (Wahyuningsih & Gitarama 2020: toksik >1,5 mg/L)',
            ],
        ];

        foreach ($rows as $row) {
            AturanAmbang::query()->updateOrCreate(
                [
                    'parameter' => $row['parameter'],
                    'zona' => $row['zona'],
                    'batas_min' => $row['batas_min'],
                    'batas_maks' => $row['batas_maks'],
                ],
                [
                    'tingkat' => $row['tingkat'],
                    'sumber_rujukan' => $row['sumber_rujukan'],
                ],
            );
        }
    }
}
