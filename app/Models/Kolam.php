<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string $jenis
 * @property float $panjang_m
 * @property float $lebar_m
 * @property float $tinggi_air_m
 * @property-read float $volume_air_m3
 * @property int $jumlah_ikan_awal
 * @property float $bobot_awal_rata_g
 * @property float $bobot_awal_sd_g
 * @property float $panjang_awal_rata_cm
 * @property float $koef_a
 * @property float|null $biomassa_awal_g
 * @property float|null $padat_tebar_per_m3
 * @property float $dosis_harian_persen
 * @property float $bobot_target_panen_g
 * @property Carbon $waktu_mulai_sandbox
 * @property Carbon $waktu_sandbox_sekarang
 * @property float $kecepatan_waktu
 * @property string $status
 * @property Carbon|null $created_at
 */
#[Fillable([
    'kode',
    'nama',
    'jenis',
    'panjang_m',
    'lebar_m',
    'tinggi_air_m',
    'jumlah_ikan_awal',
    'bobot_awal_rata_g',
    'bobot_awal_sd_g',
    'panjang_awal_rata_cm',
    'koef_a',
    'biomassa_awal_g',
    'padat_tebar_per_m3',
    'dosis_harian_persen',
    'bobot_target_panen_g',
    'waktu_mulai_sandbox',
    'waktu_sandbox_sekarang',
    'kecepatan_waktu',
    'status',
])]
class Kolam extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'panjang_m' => 'float',
            'lebar_m' => 'float',
            'tinggi_air_m' => 'float',
            'volume_air_m3' => 'float',
            'jumlah_ikan_awal' => 'integer',
            'bobot_awal_rata_g' => 'float',
            'bobot_awal_sd_g' => 'float',
            'panjang_awal_rata_cm' => 'float',
            'koef_a' => 'float',
            'biomassa_awal_g' => 'float',
            'padat_tebar_per_m3' => 'float',
            'dosis_harian_persen' => 'float',
            'bobot_target_panen_g' => 'float',
            'waktu_mulai_sandbox' => 'datetime',
            'waktu_sandbox_sekarang' => 'datetime',
            'kecepatan_waktu' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ZonaKolam, $this>
     */
    public function zonas(): HasMany
    {
        return $this->hasMany(ZonaKolam::class);
    }

    /**
     * @return HasMany<Perangkat, $this>
     */
    public function perangkats(): HasMany
    {
        return $this->hasMany(Perangkat::class);
    }

    /**
     * @return HasMany<PembacaanSensor, $this>
     */
    public function pembacaanSensors(): HasMany
    {
        return $this->hasMany(PembacaanSensor::class);
    }

    /**
     * @return HasMany<JadwalPakan, $this>
     */
    public function jadwalPakans(): HasMany
    {
        return $this->hasMany(JadwalPakan::class);
    }

    /**
     * @return HasMany<LogPakan, $this>
     */
    public function logPakans(): HasMany
    {
        return $this->hasMany(LogPakan::class);
    }

    /**
     * @return HasMany<Ikan, $this>
     */
    public function ikans(): HasMany
    {
        return $this->hasMany(Ikan::class);
    }

    /**
     * @return HasMany<ProfilLingkungan, $this>
     */
    public function profilLingkungans(): HasMany
    {
        return $this->hasMany(ProfilLingkungan::class);
    }

    /**
     * @return HasMany<SkenarioSimulasi, $this>
     */
    public function skenarioSimulasis(): HasMany
    {
        return $this->hasMany(SkenarioSimulasi::class);
    }

    /**
     * @return HasMany<SamplingPertumbuhan, $this>
     */
    public function samplingPertumbuhans(): HasMany
    {
        return $this->hasMany(SamplingPertumbuhan::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return HasMany<RekomendasiPanen, $this>
     */
    public function rekomendasiPanens(): HasMany
    {
        return $this->hasMany(RekomendasiPanen::class);
    }
}
