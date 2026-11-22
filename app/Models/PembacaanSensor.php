<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $perangkat_id
 * @property int $kolam_id
 * @property int|null $zona_id
 * @property string $parameter
 * @property float|null $nilai
 * @property string $satuan
 * @property Carbon $waktu_ukur
 * @property Carbon $waktu_diterima
 * @property string $kualitas_data
 * @property int|null $baterai_persen
 * @property int|null $sinyal_rssi
 */
#[Fillable([
    'perangkat_id',
    'kolam_id',
    'zona_id',
    'parameter',
    'nilai',
    'satuan',
    'waktu_ukur',
    'waktu_diterima',
    'kualitas_data',
    'baterai_persen',
    'sinyal_rssi',
])]
class PembacaanSensor extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai' => 'float',
            'waktu_ukur' => 'datetime',
            'waktu_diterima' => 'datetime',
            'baterai_persen' => 'integer',
            'sinyal_rssi' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Perangkat, $this>
     */
    public function perangkat(): BelongsTo
    {
        return $this->belongsTo(Perangkat::class);
    }

    /**
     * @return BelongsTo<Kolam, $this>
     */
    public function kolam(): BelongsTo
    {
        return $this->belongsTo(Kolam::class);
    }

    /**
     * @return BelongsTo<ZonaKolam, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaKolam::class, 'zona_id');
    }
}
