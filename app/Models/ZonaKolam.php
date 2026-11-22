<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $kolam_id
 * @property string $nama
 * @property int $kedalaman_cm
 * @property float $porsi_volume
 */
#[Fillable(['kolam_id', 'nama', 'kedalaman_cm', 'porsi_volume'])]
class ZonaKolam extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kedalaman_cm' => 'integer',
            'porsi_volume' => 'float',
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
     * @return HasMany<Perangkat, $this>
     */
    public function perangkats(): HasMany
    {
        return $this->hasMany(Perangkat::class, 'zona_id');
    }

    /**
     * @return HasOne<KeadaanZona, $this>
     */
    public function keadaanZona(): HasOne
    {
        return $this->hasOne(KeadaanZona::class, 'zona_id');
    }

    /**
     * @return HasMany<PembacaanSensor, $this>
     */
    public function pembacaanSensors(): HasMany
    {
        return $this->hasMany(PembacaanSensor::class, 'zona_id');
    }

    /**
     * @return HasMany<ProfilLingkungan, $this>
     */
    public function profilLingkungans(): HasMany
    {
        return $this->hasMany(ProfilLingkungan::class, 'zona_id');
    }

    /**
     * @return HasMany<Ikan, $this>
     */
    public function ikans(): HasMany
    {
        return $this->hasMany(Ikan::class, 'zona_id');
    }
}
