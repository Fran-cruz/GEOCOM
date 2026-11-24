<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combustible;
use App\Support\EstimadorCombustible;
use Illuminate\Http\Request;

class CombustibleController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate([
            'ciudad' => 'nullable|in:tegucigalpa,san_pedro_sula',
        ]);

        return Combustible::vigentes()
            ->when($datos['ciudad'] ?? null, fn ($q, $ciudad) => $q->where('ciudad', $ciudad))
            ->orderBy('ciudad')
            ->orderBy('combustible')
            ->get();
    }

    // Precios por semana para la gráfica de evolución (regular, super y diesel).
    // ?ciudad= (por defecto la de referencia) &semanas= (por defecto 12, máximo 52)
    public function historial(Request $request)
    {
        $datos = $request->validate([
            'ciudad'  => 'nullable|in:tegucigalpa,san_pedro_sula',
            'semanas' => 'nullable|integer|between:1,52',
        ]);

        $ciudad = EstimadorCombustible::ciudad($datos['ciudad'] ?? null);
        $visibles = config('combustible.visibles', ['regular', 'super', 'diesel']);

        // Fecha de la semana más antigua que entra en el rango pedido
        $desde = Combustible::where('ciudad', $ciudad)
            ->select('vigente_desde')
            ->distinct()
            ->orderByDesc('vigente_desde')
            ->limit($datos['semanas'] ?? 12)
            ->pluck('vigente_desde')
            ->min();

        $filas = $desde
            ? Combustible::where('ciudad', $ciudad)
                ->whereIn('combustible', $visibles)
                ->whereDate('vigente_desde', '>=', $desde->toDateString())
                ->orderBy('vigente_desde')
                ->get()
            : collect();

        return [
            'ciudad'  => $ciudad,
            'semanas' => $filas
                ->groupBy(fn ($f) => $f->vigente_desde->toDateString())
                ->map(fn ($grupo, $semana) => array_merge(
                    ['vigente_desde' => $semana],
                    $grupo->mapWithKeys(fn ($f) => [$f->combustible => (float) $f->precio])->all()
                ))
                ->values(),
        ];
    }
}
