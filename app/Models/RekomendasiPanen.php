<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property Carbon $tanggal_evaluasi
 * @property float $bobot_rata_g
 * @property float $skor_bobot
 * @property float $skor_air
 * @property float $skor_kesiapan
 * @property float|null $estimasi_hari_ke_target
 * @property string $status
 * @property string $alasan
 */
#[Fillable([
    'kolam_id',
    'tanggal_evaluasi',
    'bobot_rata_g',
    'skor_bobot',
    'skor_air',
    'skor_kesiapan',
    'estimasi_hari_ke_target',
    'status',
    'alasan',
])]
class RekomendasiPanen extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_evaluasi' => 'datetime',
            'bobot_rata_g' => 'float',
            'skor_bobot' => 'float',
            'skor_air' => 'float',
            'skor_kesiapan' => 'float',
            'estimasi_hari_ke_target' => 'float',
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
