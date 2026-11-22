<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string $parameter
 * @property string $satuan
 * @property float|null $nilai_min_fisik
 * @property float|null $nilai_maks_fisik
 * @property int $interval_default_menit
 * @property string $tingkat
 * @property float $noise_sd
 * @property float $peluang_error
 */
#[Fillable([
    'kode',
    'nama',
    'parameter',
    'satuan',
    'nilai_min_fisik',
    'nilai_maks_fisik',
    'interval_default_menit',
    'tingkat',
    'noise_sd',
    'peluang_error',
])]
class JenisPerangkat extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai_min_fisik' => 'float',
            'nilai_maks_fisik' => 'float',
            'interval_default_menit' => 'integer',
            'noise_sd' => 'float',
            'peluang_error' => 'float',
        ];
    }

    /**
     * @return HasMany<Perangkat, $this>
     */
    public function perangkats(): HasMany
    {
        return $this->hasMany(Perangkat::class);
    }
}
