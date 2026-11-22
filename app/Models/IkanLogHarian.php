<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ikan_id
 * @property Carbon $tanggal_sandbox
 * @property float $bobot_g
 * @property float $panjang_cm
 * @property float $sgr_hari_persen
 * @property float $jam_di_atas
 * @property float $jam_di_bawah
 * @property float $suhu_terpapar
 * @property float $ph_terpapar
 * @property float $do_terpapar
 * @property float $amonia_terpapar
 * @property float $pakan_dimakan_g
 * @property float $faktor_ransum
 * @property float $stres
 */
#[Fillable([
    'ikan_id',
    'tanggal_sandbox',
    'bobot_g',
    'panjang_cm',
    'sgr_hari_persen',
    'jam_di_atas',
    'jam_di_bawah',
    'suhu_terpapar',
    'ph_terpapar',
    'do_terpapar',
    'amonia_terpapar',
    'pakan_dimakan_g',
    'faktor_ransum',
    'stres',
])]
class IkanLogHarian extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_sandbox' => 'date',
            'bobot_g' => 'float',
            'panjang_cm' => 'float',
            'sgr_hari_persen' => 'float',
            'jam_di_atas' => 'float',
            'jam_di_bawah' => 'float',
            'suhu_terpapar' => 'float',
            'ph_terpapar' => 'float',
            'do_terpapar' => 'float',
            'amonia_terpapar' => 'float',
            'pakan_dimakan_g' => 'float',
            'faktor_ransum' => 'float',
            'stres' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Ikan, $this>
     */
    public function ikan(): BelongsTo
    {
        return $this->belongsTo(Ikan::class);
    }
}
