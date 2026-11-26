<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\DetalleOrden;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Services\ReporteExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        return $this->armarResumen($request);
    }

    // Descarga el reporte en Excel. Reutiliza los mismos datos que los endpoints
    // de resumen y detalle, así el Excel siempre coincide con lo que muestra la app.
    // Filtros: ?tipo=resumen|conductor|gasolinera&id=&desde=&hasta=&conductor_id=&gasolinera_id=
    public function exportar(Request $request, ReporteExcel $excel)
    {
        $request->validate([
            'tipo' => 'required|in:resumen,conductor,gasolinera',
            'id' => 'required_if:tipo,conductor,gasolinera|nullable|integer|min:1',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'conductor_id' => 'nullable|integer|min:1',
            'gasolinera_id' => 'nullable|integer|min:1',
        ]);

        $tipo = $request->input('tipo');

        // findOrFail dentro de los detalles responde 404 si el id no existe
        $datos = match ($tipo) {
            'conductor' => $this->detalleConductor($request, $request->integer('id')),
            'gasolinera' => $this->detalleGasolinera($request, $request->integer('id')),
            default => $this->armarResumen($request, true),
        };

        $libro = $excel->generar($tipo, $datos, $request->only(['desde', 'hasta', 'conductor_id', 'gasolinera_id']));

        $nombre = sprintf(
            'reporte_%s_%s_%s.xlsx',
            $tipo,
            $request->filled('desde') ? Carbon::parse($request->input('desde'))->toDateString() : 'inicio',
            $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->toDateString() : Carbon::today()->toDateString()
        );

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
            'por_vehiculo' => $this->porVehiculo($lineas),
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

    // Datos del resumen general. Con $conOrdenes agrega una fila por línea (para el Excel).
    private function armarResumen(Request $request, bool $conOrdenes = false): array
    {
        $lineas = $this->lineas($request);

        $datos = [
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

        if ($conOrdenes) {
            $datos['ordenes'] = $this->listaOrdenes($lineas);
        }

        return $datos;
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
            ] + $this->consumo($lineas);
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
                ] + $this->consumo($grupo))
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
                ] + $this->consumo($grupo))
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
                ] + $this->consumo($grupo))
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
                'precio_galon' => $linea->precio_galon !== null ? (float) $linea->precio_galon : null,
                'galones_estimados' => $linea->galones_estimados !== null ? (float) $linea->galones_estimados : null,
                'km_estimados' => $linea->km_estimados !== null ? (float) $linea->km_estimados : null,
            ])
            ->values()
            ->all();
    }

    // Galones y km estimados con el precio guardado en cada línea al asignar la orden.
    // Las líneas sin precio (antes de tener scraper) no cuentan para el promedio.
    private function consumo($lineas): array
    {
        $conPrecio = $lineas->filter(fn ($linea) => $linea->galones_estimados !== null);
        $galones = (float) $conPrecio->sum('galones_estimados');

        return [
            'galones_total' => round($galones, 2),
            'km_total' => round((float) $conPrecio->sum('km_estimados'), 2),
            'precio_promedio_galon' => $galones > 0
                ? round((float) $conPrecio->sum('monto') / $galones, 2)
                : null,
        ];
    }

    // Gasto, galones y km por vehículo (solo líneas de vehículo, no MGP).
    private function porVehiculo($lineas): array
    {
        return $lineas
            ->where('tipo_destino', 'vehiculo')
            ->filter(fn ($linea) => $linea->vehiculo_id !== null)
            ->groupBy('vehiculo_id')
            ->map(fn ($grupo, $id) => [
                    'id' => (int) $id,
                    'placa' => $grupo->first()->vehiculo?->placa ?? 'Vehículo',
                    'descripcion' => trim(($grupo->first()->vehiculo?->marca ?? '') . ' ' . ($grupo->first()->vehiculo?->modelo ?? '')),
                    'rendimiento_km_litro' => $grupo->first()->vehiculo ? (float) $grupo->first()->vehiculo->rendimiento_km_litro : null,
                    'monto_total' => round((float) $grupo->sum('monto'), 2),
                    'cantidad_ordenes' => $grupo->pluck('orden_id')->unique()->count(),
                ] + $this->consumo($grupo))
            ->sortByDesc('monto_total')
            ->values()
            ->all();
    }
}
