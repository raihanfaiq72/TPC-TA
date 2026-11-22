<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property Carbon $tanggal
 * @property int $jumlah_sampel
 * @property int $jumlah_mati
 * @property float $bobot_total_sampel_g
 * @property-read float $bobot_rata_g
 * @property float $panjang_rata_cm
 * @property Carbon|null $dibuat_pada
 */
#[Fillable([
    'kolam_id',
    'tanggal',
    'jumlah_sampel',
    'jumlah_mati',
    'bobot_total_sampel_g',
    'panjang_rata_cm',
])]
class SamplingPertumbuhan extends Model
{
    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_sampel' => 'integer',
            'jumlah_mati' => 'integer',
            'bobot_total_sampel_g' => 'float',
            'bobot_rata_g' => 'float',
            'panjang_rata_cm' => 'float',
            'dibuat_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Kolam, $this>
     */
    public function kolam(): BelongsTo
    {
        return $this->belongsTo(Kolam::class);
    }
}
