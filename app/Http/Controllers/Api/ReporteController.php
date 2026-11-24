<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\DetalleOrden;
use App\Models\Gasolinera;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    // Total gastado y cantidad de órdenes completadas por conductor.
    // Filtros: ?desde=&hasta=&conductor_id=&gasolinera_id=
    public function porConductor(Request $request)
    {
        return $this->base($request)
            ->join('conductores', 'conductores.id', '=', 'ordenes.conductor_id')
            ->join('users', 'users.id', '=', 'conductores.usuario_id')
            ->groupBy('conductores.id', 'users.name')
            ->selectRaw('conductores.id as conductor_id, users.name as nombre_conductor,
                         COUNT(DISTINCT ordenes.id) as cantidad_ordenes,
                         SUM(detalle_ordenes.monto) as monto_total')
            ->get();
    }

    // Lo mismo, agrupado por gasolinera.
    // Filtros: ?desde=&hasta=&conductor_id=&gasolinera_id=
    public function porGasolinera(Request $request)
    {
        return $this->base($request)
            ->join('gasolineras', 'gasolineras.id', '=', 'ordenes.gasolinera_id')
            ->groupBy('gasolineras.id', 'gasolineras.nombre')
            ->selectRaw('gasolineras.id as gasolinera_id, gasolineras.nombre as nombre_gasolinera,
                         COUNT(DISTINCT ordenes.id) as cantidad_ordenes,
                         SUM(detalle_ordenes.monto) as monto_total')
            ->get();
    }

    // Resumen general del periodo: indicadores, serie por mes y rankings.
    public function resumen(Request $request)
    {
        $lineas = $this->lineas($request);

        return [
            'resumen' => $this->indicadores($lineas, $this->contarVencidas($request)),
            'por_mes' => $this->porMes($lineas),
            'por_combustible' => $this->porCombustible($lineas),
            'top_conductores' => $this->ranking(
                $lineas,
                fn ($linea) => $linea->orden->conductor_id,
                fn ($linea) => $linea->orden->conductor->usuario->name
            ),
            'top_gasolineras' => $this->ranking(
                $lineas,
                fn ($linea) => $linea->orden->gasolinera_id,
                fn ($linea) => $linea->orden->gasolinera->nombre
            ),
        ];
    }

    // Reporte completo de un conductor.
    public function detalleConductor(Request $request, $id)
    {
        $conductor = Conductor::with('usuario')->findOrFail($id);
        $lineas = $this->lineas($request, conductorId: (int) $conductor->id);

        return [
            'conductor' => [
                'id' => $conductor->id,
                'nombre' => $conductor->usuario->name,
                'cargo' => $conductor->cargo,
                'activo' => (bool) $conductor->activo,
            ],
            'resumen' => $this->indicadores(
                $lineas,
                $this->contarVencidas($request, conductorId: (int) $conductor->id)
            ),
            'por_mes' => $this->porMes($lineas),
            'por_combustible' => $this->porCombustible($lineas),
            'por_gasolinera' => $this->ranking(
                $lineas,
                fn ($linea) => $linea->orden->gasolinera_id,
                fn ($linea) => $linea->orden->gasolinera->nombre
            ),
            'ordenes' => $this->listaOrdenes($lineas),
        ];
    }

    // Reporte completo de una gasolinera.
    public function detalleGasolinera(Request $request, $id)
    {
        $gasolinera = Gasolinera::findOrFail($id);
        $lineas = $this->lineas($request, gasolineraId: (int) $gasolinera->id);

        return [
            'gasolinera' => [
                'id' => $gasolinera->id,
                'nombre' => $gasolinera->nombre,
                'direccion' => $gasolinera->direccion,
                'activo' => (bool) $gasolinera->activo,
            ],
            'resumen' => $this->indicadores(
                $lineas,
                $this->contarVencidas($request, gasolineraId: (int) $gasolinera->id)
            ),
            'por_mes' => $this->porMes($lineas),
            'por_combustible' => $this->porCombustible($lineas),
            'por_conductor' => $this->ranking(
                $lineas,
                fn ($linea) => $linea->orden->conductor_id,
                fn ($linea) => $linea->orden->conductor->usuario->name
            ),
            'ordenes' => $this->listaOrdenes($lineas),
        ];
    }

    // Consultas agrupadas de los endpoints existentes.
    // Solo incluye órdenes completadas, usando la fecha de cierre.
    private function base(Request $request)
    {
        return DetalleOrden::query()
            ->join('ordenes', 'ordenes.id', '=', 'detalle_ordenes.orden_id')
            ->whereIn('ordenes.estado', ['completada', 'completada_tarde'])
            ->when($request->input('desde'), fn ($query, $value) => $query->whereDate('ordenes.fecha_completada', '>=', $value))
            ->when($request->input('hasta'), fn ($query, $value) => $query->whereDate('ordenes.fecha_completada', '<=', $value))
            ->when($request->input('conductor_id'), fn ($query, $value) => $query->where('ordenes.conductor_id', $value))
            ->when($request->input('gasolinera_id'), fn ($query, $value) => $query->where('ordenes.gasolinera_id', $value));
    }

    // Obtiene líneas y relaciones. Los filtros del detalle siempre respetan
    // el ID de la entidad consultada; en resumen usa los filtros opcionales.
    private function lineas(Request $request, ?int $conductorId = null, ?int $gasolineraId = null)
    {
        $conductorId = $conductorId ?? ($request->filled('conductor_id') ? (int) $request->input('conductor_id') : null);
        $gasolineraId = $gasolineraId ?? ($request->filled('gasolinera_id') ? (int) $request->input('gasolinera_id') : null);

        return DetalleOrden::with(['orden.gasolinera', 'orden.conductor.usuario', 'vehiculo'])
            ->whereHas('orden', function ($query) use ($request, $conductorId, $gasolineraId) {
                $query->whereIn('estado', ['completada', 'completada_tarde'])
                    ->when($request->input('desde'), fn ($q, $value) => $q->whereDate('fecha_completada', '>=', $value))
                    ->when($request->input('hasta'), fn ($q, $value) => $q->whereDate('fecha_completada', '<=', $value))
                    ->when($conductorId, fn ($q, $value) => $q->where('conductor_id', $value))
                    ->when($gasolineraId, fn ($q, $value) => $q->where('gasolinera_id', $value));
            })
            ->get();
    }

    // Órdenes vencidas del periodo; se usa fecha de asignación.
    private function contarVencidas(Request $request, ?int $conductorId = null, ?int $gasolineraId = null): int
    {
        $conductorId = $conductorId ?? ($request->filled('conductor_id') ? (int) $request->input('conductor_id') : null);
        $gasolineraId = $gasolineraId ?? ($request->filled('gasolinera_id') ? (int) $request->input('gasolinera_id') : null);

        return Orden::where('estado', 'vencida')
            ->when($request->input('desde'), fn ($query, $value) => $query->whereDate('fecha_asignacion', '>=', $value))
            ->when($request->input('hasta'), fn ($query, $value) => $query->whereDate('fecha_asignacion', '<=', $value))
            ->when($conductorId, fn ($query, $value) => $query->where('conductor_id', $value))
            ->when($gasolineraId, fn ($query, $value) => $query->where('gasolinera_id', $value))
            ->count();
    }

    private function indicadores($lineas, int $vencidas): array
    {
        $ordenes = $lineas->pluck('orden')->unique('id');
        $total = (float) $lineas->sum('monto');
        $cantidad = $ordenes->count();
        $tarde = $ordenes->where('estado', 'completada_tarde')->count();
        $solicitadas = $cantidad + $vencidas;

        return [
            'monto_total' => round($total, 2),
            'cantidad_ordenes' => $cantidad,
            'promedio_orden' => $cantidad ? round($total / $cantidad, 2) : 0,
            'completadas_tarde' => $tarde,
            'vencidas' => $vencidas,
            'cumplimiento_pct' => $solicitadas
                ? round((($cantidad - $tarde) / $solicitadas) * 100, 1)
                : 0,
        ];
    }

    // Serie mensual para la gráfica de barras.
    private function porMes($lineas): array
    {
        return $lineas
            ->groupBy(fn ($linea) => Carbon::parse($linea->orden->fecha_completada)->format('Y-m'))
            ->map(fn ($grupo, $mes) => [
                'mes' => $mes,
                'monto_total' => round((float) $grupo->sum('monto'), 2),
                'cantidad_ordenes' => $grupo->pluck('orden_id')->unique()->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function porCombustible($lineas): array
    {
        return $lineas
            ->groupBy('tipo_combustible')
            ->map(fn ($grupo, $tipo) => [
                'tipo_combustible' => $tipo,
                'monto_total' => round((float) $grupo->sum('monto'), 2),
                'cantidad_ordenes' => $grupo->pluck('orden_id')->unique()->count(),
            ])
            ->values()
            ->all();
    }

    // Ranking genérico, ordenado por monto total descendente.
    private function ranking($lineas, callable $id, callable $nombre): array
    {
        return $lineas
            ->groupBy($id)
            ->map(fn ($grupo, $key) => [
                'id' => (int) $key,
                'nombre' => $nombre($grupo->first()),
                'monto_total' => round((float) $grupo->sum('monto'), 2),
                'cantidad_ordenes' => $grupo->pluck('orden_id')->unique()->count(),
            ])
            ->sortByDesc('monto_total')
            ->values()
            ->all();
    }

    private function listaOrdenes($lineas): array
    {
        return $lineas
            ->sortByDesc(fn ($linea) => $linea->orden->fecha_completada)
            ->map(fn ($linea) => [
                'orden_id' => $linea->orden_id,
                'numero_orden' => $linea->orden->numero_orden,
                'fecha_completada' => Carbon::parse($linea->orden->fecha_completada)->toIso8601String(),
                'estado' => $linea->orden->estado,
                'gasolinera' => $linea->orden->gasolinera->nombre,
                'conductor' => $linea->orden->conductor->usuario->name,
                'destino' => $linea->tipo_destino === 'vehiculo'
                    ? ($linea->vehiculo?->placa ?? 'Vehículo')
                    : ($linea->descripcion_mgp ?: 'MGP'),
                'tipo_combustible' => $linea->tipo_combustible,
                'monto' => (float) $linea->monto,
            ])
            ->values()
            ->all();
    }
}
