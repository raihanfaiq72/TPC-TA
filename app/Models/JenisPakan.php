<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $merek
 * @property string $bentuk
 * @property float $protein_persen
 * @property float $lemak_persen
 */
#[Fillable(['merek', 'bentuk', 'protein_persen', 'lemak_persen'])]
class JenisPakan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'protein_persen' => 'float',
            'lemak_persen' => 'float',
        ];
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
}
