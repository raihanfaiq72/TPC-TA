<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property int $urutan
 * @property int $jumlah_tebar
 * @property float $target_bobot_g
 * @property string $status
 * @property Carbon|null $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 * @property float|null $bobot_akhir_g
 * @property float|null $biomassa_akhir_g
 * @property int $jumlah_mati
 */
#[Fillable([
    'kolam_id',
    'urutan',
    'jumlah_tebar',
    'target_bobot_g',
    'status',
    'tanggal_mulai',
    'tanggal_selesai',
    'bobot_akhir_g',
    'biomassa_akhir_g',
    'jumlah_mati',
])]
class SiklusKolam extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'jumlah_tebar' => 'integer',
            'target_bobot_g' => 'float',
            'tanggal_mulai' => 'datetime',
            'tanggal_selesai' => 'datetime',
            'bobot_akhir_g' => 'float',
            'biomassa_akhir_g' => 'float',
            'jumlah_mati' => 'integer',
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
