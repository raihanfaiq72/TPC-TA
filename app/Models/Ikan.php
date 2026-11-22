<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property string $kode
 * @property float $faktor_genetik
 * @property float $preferensi_zona
 * @property float $bobot_awal_g
 * @property float $panjang_awal_cm
 * @property float $bobot_g
 * @property float $panjang_cm
 * @property int $zona_id
 * @property float $isi_lambung_persen
 * @property float $stres
 * @property string $status
 * @property Carbon|null $tanggal_mati
 * @property string|null $penyebab_mati
 * @property int $hari_tick_atas
 * @property int $hari_tick_bawah
 * @property float $hari_sum_suhu
 * @property float $hari_sum_ph
 * @property float $hari_sum_do
 * @property float $hari_sum_amonia
 * @property float $hari_pakan_g
 */
#[Fillable([
    'kolam_id',
    'kode',
    'faktor_genetik',
    'preferensi_zona',
    'bobot_awal_g',
    'panjang_awal_cm',
    'bobot_g',
    'panjang_cm',
    'zona_id',
    'isi_lambung_persen',
    'stres',
    'status',
    'tanggal_mati',
    'penyebab_mati',
    'hari_tick_atas',
    'hari_tick_bawah',
    'hari_sum_suhu',
    'hari_sum_ph',
    'hari_sum_do',
    'hari_sum_amonia',
    'hari_pakan_g',
])]
class Ikan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'faktor_genetik' => 'float',
            'preferensi_zona' => 'float',
            'bobot_awal_g' => 'float',
            'panjang_awal_cm' => 'float',
            'bobot_g' => 'float',
            'panjang_cm' => 'float',
            'isi_lambung_persen' => 'float',
            'stres' => 'float',
            'tanggal_mati' => 'date',
            'hari_tick_atas' => 'integer',
            'hari_tick_bawah' => 'integer',
            'hari_sum_suhu' => 'float',
            'hari_sum_ph' => 'float',
            'hari_sum_do' => 'float',
            'hari_sum_amonia' => 'float',
            'hari_pakan_g' => 'float',
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
     * @return HasMany<IkanLogHarian, $this>
     */
    public function logHarians(): HasMany
    {
        return $this->hasMany(IkanLogHarian::class);
    }
}
