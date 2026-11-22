<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $zona_id
 * @property int $kolam_id
 * @property float $suhu_asli
 * @property float $ph_asli
 * @property float $do_asli
 * @property float $amonia_asli
 * @property float $sisa_pakan_g
 * @property Carbon $diperbarui_pada
 */
#[Fillable([
    'zona_id',
    'kolam_id',
    'suhu_asli',
    'ph_asli',
    'do_asli',
    'amonia_asli',
    'sisa_pakan_g',
    'diperbarui_pada',
])]
class KeadaanZona extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'zona_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suhu_asli' => 'float',
            'ph_asli' => 'float',
            'do_asli' => 'float',
            'amonia_asli' => 'float',
            'sisa_pakan_g' => 'float',
            'diperbarui_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ZonaKolam, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaKolam::class, 'zona_id');
    }

    /**
     * @return BelongsTo<Kolam, $this>
     */
    public function kolam(): BelongsTo
    {
        return $this->belongsTo(Kolam::class);
    }
}
