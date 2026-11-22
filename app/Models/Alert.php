<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property int|null $zona_id
 * @property int $aturan_id
 * @property string $parameter
 * @property float $nilai
 * @property string $tingkat
 * @property Carbon $waktu
 * @property string $status
 */
#[Fillable([
    'kolam_id',
    'zona_id',
    'aturan_id',
    'parameter',
    'nilai',
    'tingkat',
    'waktu',
    'status',
])]
class Alert extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nilai' => 'float',
            'waktu' => 'datetime',
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
     * @return BelongsTo<AturanAmbang, $this>
     */
    public function aturan(): BelongsTo
    {
        return $this->belongsTo(AturanAmbang::class, 'aturan_id');
    }
}
