<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property string $jenis_kejadian
 * @property float $besar_dampak
 * @property Carbon $mulai
 * @property Carbon $selesai
 * @property bool $sudah_diterapkan
 */
#[Fillable([
    'kolam_id',
    'jenis_kejadian',
    'besar_dampak',
    'mulai',
    'selesai',
    'sudah_diterapkan',
])]
class SkenarioSimulasi extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'besar_dampak' => 'float',
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'sudah_diterapkan' => 'boolean',
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
