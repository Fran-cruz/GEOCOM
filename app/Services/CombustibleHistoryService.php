<?php

namespace App\Services;

use App\Models\Combustible;

class CombustibleHistoryService
{
    public function obtener(?string $ciudad = null, int $semanas = 12): array
    {
        $ciudad = CombustibleEstimator::ciudad($ciudad);
        $visibles = config('combustible.visibles', ['regular', 'super', 'diesel']);
        $desde = Combustible::where('ciudad', $ciudad)
            ->select('vigente_desde')
            ->distinct()
            ->orderByDesc('vigente_desde')
            ->limit($semanas)
            ->pluck('vigente_desde')
            ->min();

        $filas = $desde
            ? Combustible::where('ciudad', $ciudad)
                ->whereIn('combustible', $visibles)
                ->whereDate('vigente_desde', '>=', $desde)
                ->orderBy('vigente_desde')
                ->get()
            : collect();

        return [
            'ciudad' => $ciudad,
            'semanas' => $filas
                ->groupBy(fn (Combustible $precio) => $precio->vigente_desde->toDateString())
                ->map(fn ($grupo, $semana) => array_merge(
                    ['vigente_desde' => $semana],
                    $grupo->mapWithKeys(fn (Combustible $precio) => [
                        $precio->combustible => (float) $precio->precio,
                    ])->all()
                ))
                ->values(),
        ];
    }
}
