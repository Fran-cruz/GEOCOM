<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Combustible extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'combustibles';

    protected $guarded = [];

    protected $casts = [
        'vigente_desde' => 'date',
        'precio' => 'decimal:2',
        'variacion' => 'decimal:2',
        'apoyo' => 'decimal:2',
    ];

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('vigente_desde', static::query()->max('vigente_desde'));
    }

    public static function precioVigente(string $combustible, string $ciudad): ?self
    {
        return static::query()
            ->vigentes()
            ->where('combustible', $combustible)
            ->where('ciudad', $ciudad)
            ->first();
    }
}
