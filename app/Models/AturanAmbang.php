<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $parameter
 * @property string|null $zona
 * @property float|null $batas_min
 * @property float|null $batas_maks
 * @property string $tingkat
 * @property string $sumber_rujukan
 */
#[Fillable([
    'parameter',
    'zona',
    'batas_min',
    'batas_maks',
    'tingkat',
    'sumber_rujukan',
])]
class AturanAmbang extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'batas_min' => 'float',
            'batas_maks' => 'float',
        ];
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'aturan_id');
    }
}
