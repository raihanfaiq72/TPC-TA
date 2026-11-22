<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $kolam_id
 * @property int $zona_id
 * @property string $parameter
 * @property float $nilai_dasar
 * @property float $amplitudo_harian
 * @property float $offset_zona
 */
#[Fillable([
    'kolam_id',
    'zona_id',
    'parameter',
    'nilai_dasar',
    'amplitudo_harian',
    'offset_zona',
])]
class ProfilLingkungan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai_dasar' => 'float',
            'amplitudo_harian' => 'float',
            'offset_zona' => 'float',
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
}
