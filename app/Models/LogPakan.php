<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolam_id
 * @property int|null $perangkat_id
 * @property int|null $jadwal_id
 * @property int $jenis_pakan_id
 * @property Carbon $waktu
 * @property float $jumlah_g
 * @property string $pemicu
 */
#[Fillable([
    'kolam_id',
    'perangkat_id',
    'jadwal_id',
    'jenis_pakan_id',
    'waktu',
    'jumlah_g',
    'pemicu',
])]
class LogPakan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'jumlah_g' => 'float',
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
     * @return BelongsTo<Perangkat, $this>
     */
    public function perangkat(): BelongsTo
    {
        return $this->belongsTo(Perangkat::class);
    }

    /**
     * @return BelongsTo<JadwalPakan, $this>
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPakan::class, 'jadwal_id');
    }

    /**
     * @return BelongsTo<JenisPakan, $this>
     */
    public function jenisPakan(): BelongsTo
    {
        return $this->belongsTo(JenisPakan::class);
    }
}
