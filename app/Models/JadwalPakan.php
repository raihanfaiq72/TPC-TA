<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $kolam_id
 * @property int $jenis_pakan_id
 * @property string $jam
 * @property float $porsi_dosis_persen
 * @property bool $aktif
 */
#[Fillable([
    'kolam_id',
    'jenis_pakan_id',
    'jam',
    'porsi_dosis_persen',
    'aktif',
])]
class JadwalPakan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porsi_dosis_persen' => 'float',
            'aktif' => 'boolean',
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
     * @return BelongsTo<JenisPakan, $this>
     */
    public function jenisPakan(): BelongsTo
    {
        return $this->belongsTo(JenisPakan::class);
    }

    /**
     * @return HasMany<LogPakan, $this>
     */
    public function logPakans(): HasMany
    {
        return $this->hasMany(LogPakan::class, 'jadwal_id');
    }
}
