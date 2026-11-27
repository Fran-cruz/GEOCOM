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

    public static function precioEn(string $combustible, string $ciudad, $fecha): ?self
    {
        $dia = \Illuminate\Support\Carbon::parse($fecha)->toDateString();
        $base = static::query()->where('combustible', $combustible)->where('ciudad', $ciudad);

        return (clone $base)->whereDate('vigente_desde', '<=', $dia)->orderByDesc('vigente_desde')->first()
            ?? (clone $base)->orderBy('vigente_desde')->first();
    }
}
