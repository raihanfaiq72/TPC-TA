<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kode
 * @property int $kolam_id
 * @property int|null $zona_id
 * @property int $jenis_perangkat_id
 * @property string $status
 * @property int $interval_menit
 * @property Carbon|null $terakhir_baca
 * @property float $baterai_persen
 * @property Carbon|null $tanggal_kalibrasi
 */
#[Fillable([
    'kode',
    'kolam_id',
    'zona_id',
    'jenis_perangkat_id',
    'status',
    'interval_menit',
    'terakhir_baca',
    'baterai_persen',
    'tanggal_kalibrasi',
])]
class Perangkat extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_menit' => 'integer',
            'terakhir_baca' => 'datetime',
            'baterai_persen' => 'float',
            'tanggal_kalibrasi' => 'date',
        ];
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

    /**
     * @return BelongsTo<JenisPerangkat, $this>
     */
    public function jenisPerangkat(): BelongsTo
    {
        return $this->belongsTo(JenisPerangkat::class);
    }

    /**
     * @return HasMany<PembacaanSensor, $this>
     */
    public function pembacaanSensors(): HasMany
    {
        return $this->hasMany(PembacaanSensor::class);
    }

    /**
     * @return HasMany<LogPakan, $this>
     */
    public function logPakans(): HasMany
    {
        return $this->hasMany(LogPakan::class);
    }
}
