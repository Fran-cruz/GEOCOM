<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Models\Conductor;
use App\Models\DetalleOrden;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Models\Vehiculo;
use App\Services\CombustibleEstimator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteWebController extends Controller
{
    use ValidatesWebRequests;

    private const ESTADOS_ORDEN = [
        'pendiente',
        'abierta',
        'abierta_tarde',
        'completada',
        'completada_tarde',
        'vencida',
        'cancelada',
    ];

    private const ESTADOS_EJECUTADOS = ['completada', 'completada_tarde'];

    public function index(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        $datos = $this->calcularReporte($filtros);

        return Inertia::render('Reportes/Index', [
            'filtros' => $filtros,
            ...$datos,
            'export_pdf_url' => route('web.reportes.pdf', $filtros),
        ]);
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        $datos = $this->calcularReporte($filtros);
        $ordenes = $this->ordenesFiltradas($filtros)
            ->with([
                'conductor.usuario:id,name',
                'gasolinera',
                'detalles' => fn ($query) => $query
                    ->with('vehiculo')
                    ->when($filtros['vehiculo_id'] ?? null, fn ($detalle, $id) => $detalle->where('vehiculo_id', $id)),
            ])
            ->orderBy('fecha_asignacion')
            ->get();
        $this->adjuntarEstimaciones($ordenes);

        return Pdf::loadView('pdf.reporte-ordenes', [
            ...$datos,
            'ordenes' => $ordenes,
            'generadoEn' => now()->format('d/m/Y H:i'),
            'usuario' => $request->user(),
            'filtrosAplicados' => $this->describirFiltros($filtros),
            'etiquetasEstados' => [
                'pendiente' => 'Pendiente',
                'abierta' => 'Abierta',
                'abierta_tarde' => 'Abierta tarde',
                'completada' => 'Completada',
                'completada_tarde' => 'Completada tarde',
                'vencida' => 'Vencida',
                'cancelada' => 'Cancelada',
            ],
            'estadosEjecutados' => self::ESTADOS_EJECUTADOS,
        ])
            ->setPaper('a4', 'landscape')
            ->download('reporte-ordenes-'.now()->format('Ymd-His').'.pdf');
    }

    private function validarFiltros(Request $request): array
    {
        return $this->validateWeb($request, [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS_ORDEN)],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'conductor_id' => ['nullable', 'integer', 'exists:conductores,id'],
            'gasolinera_id' => ['nullable', 'integer', 'exists:gasolineras,id'],
        ], [
            'desde.date' => 'La fecha inicial no es válida.',
            'hasta.date' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'vehiculo_id.exists' => 'El vehículo seleccionado no existe.',
            'conductor_id.exists' => 'El conductor seleccionado no existe.',
            'gasolinera_id.exists' => 'La gasolinera seleccionada no existe.',
        ]);
    }

    private function calcularReporte(array $filtros): array
    {
        $ordenes = $this->ordenesFiltradas($filtros)->get();
        $porEstado = array_replace(
            array_fill_keys(self::ESTADOS_ORDEN, 0),
            $ordenes->countBy('estado')->all()
        );
        $ordenesEjecutadas = $this->ordenesEjecutadasFiltradas($filtros)
            ->with([
                'conductor.usuario:id,name',
                'gasolinera',
                'detalles' => fn ($query) => $query
                    ->with('vehiculo')
                    ->when($filtros['vehiculo_id'] ?? null, fn ($detalle, $id) => $detalle->where('vehiculo_id', $id)),
            ])
            ->get();
        $lineas = $ordenesEjecutadas->flatMap(
            fn (Orden $orden) => $orden->detalles->map(fn (DetalleOrden $detalle) => [
                'detalle' => $detalle,
                'orden' => $orden,
                ...$this->calcularEstimacion($detalle, $orden),
            ])
        );
        $montoTotal = (float) $lineas->sum(fn (array $linea) => (float) $linea['detalle']->monto);
        $montoConPrecio = (float) $lineas
            ->filter(fn (array $linea) => $linea['galones_estimados'] !== null)
            ->sum(fn (array $linea) => (float) $linea['detalle']->monto);
        $galonesTotal = (float) $lineas->sum(fn (array $linea) => $linea['galones_estimados'] ?? 0);
        $litrosTotal = (float) $lineas->sum(fn (array $linea) => $linea['litros_estimados'] ?? 0);
        $kmTotal = (float) $lineas->sum(fn (array $linea) => $linea['km_estimados'] ?? 0);
        $cantidadOrdenes = $ordenesEjecutadas->unique('id')->count();
        $cumplidasATiempo = $ordenesEjecutadas->where('estado', 'completada')->count();
        $completadasTarde = $ordenesEjecutadas->where('estado', 'completada_tarde')->count();
        $vencidas = $porEstado['vencida'];
        $solicitadas = $cantidadOrdenes + $vencidas;
        $porCombustible = $lineas
            ->groupBy(fn (array $linea) => $linea['detalle']->tipo_combustible)
            ->map(fn ($grupo, $tipoCombustible) => $this->resumenCombustible($grupo, $tipoCombustible))
            ->values()
            ->all();
        $topConductores = $this->rankingLineas($lineas, fn (array $linea) => $linea['orden']->conductor_id, fn (array $linea) => $linea['orden']->conductor?->usuario?->name);
        $topGasolineras = $this->rankingLineas($lineas, fn (array $linea) => $linea['orden']->gasolinera_id, fn (array $linea) => $linea['orden']->gasolinera?->nombre);
        $porConductor = collect($topConductores)->map(fn (array $item) => [
            'conductor_id' => $item['id'],
            'nombre_conductor' => $item['nombre'],
            'cantidad_ordenes' => $item['cantidad_ordenes'],
            'monto_total' => $item['monto_total'],
        ])->all();
        $porGasolinera = collect($topGasolineras)->map(fn (array $item) => [
            'gasolinera_id' => $item['id'],
            'nombre_gasolinera' => $item['nombre'],
            'cantidad_ordenes' => $item['cantidad_ordenes'],
            'monto_total' => $item['monto_total'],
        ])->all();

        return [
            'resumen' => [
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
                'ordenes_ejecutadas' => $cantidadOrdenes,
                'monto_consumido' => $montoTotal,
                'monto_total' => round($montoTotal, 2),
                'cantidad_ordenes' => $cantidadOrdenes,
                'promedio_orden' => $cantidadOrdenes ? round($montoTotal / $cantidadOrdenes, 2) : 0,
                'galones_total' => round($galonesTotal, 2),
                'litros_total' => round($litrosTotal, 2),
                'km_total' => round($kmTotal, 2),
                'precio_promedio_galon' => $galonesTotal ? round($montoConPrecio / $galonesTotal, 2) : null,
                'cumplidas_a_tiempo' => $cumplidasATiempo,
                'completadas_tarde' => $completadasTarde,
                'vencidas' => $vencidas,
                'cumplimiento_pct' => $solicitadas
                    ? round(($cumplidasATiempo / $solicitadas) * 100, 1)
                    : 0,
            ],
            'porConductor' => $porConductor,
            'porGasolinera' => $porGasolinera,
            'por_combustible' => $porCombustible,
            'por_mes' => $this->porMes($lineas),
            'por_vehiculo' => $this->porVehiculo($lineas),
            'top_conductores' => $topConductores,
            'top_gasolineras' => $topGasolineras,
        ];
    }

    private function ordenesFiltradas(array $filtros)
    {
        return Orden::query()
            ->when($filtros['desde'] ?? null, fn ($query, $fecha) => $query->whereDate('ordenes.fecha_asignacion', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($query, $fecha) => $query->whereDate('ordenes.fecha_asignacion', '<=', $fecha))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('ordenes.estado', $estado))
            ->when($filtros['conductor_id'] ?? null, fn ($query, $id) => $query->where('ordenes.conductor_id', $id))
            ->when($filtros['gasolinera_id'] ?? null, fn ($query, $id) => $query->where('ordenes.gasolinera_id', $id))
            ->when($filtros['vehiculo_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'detalles',
                fn ($detalle) => $detalle->where('vehiculo_id', $id)
            ));
    }

    private function ordenesEjecutadasFiltradas(array $filtros)
    {
        return Orden::query()
            ->whereIn('estado', self::ESTADOS_EJECUTADOS)
            ->when($filtros['desde'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_completada', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_completada', '<=', $fecha))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filtros['conductor_id'] ?? null, fn ($query, $id) => $query->where('conductor_id', $id))
            ->when($filtros['gasolinera_id'] ?? null, fn ($query, $id) => $query->where('gasolinera_id', $id))
            ->when($filtros['vehiculo_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'detalles',
                fn ($detalle) => $detalle->where('vehiculo_id', $id)
            ));
    }

    private function calcularEstimacion(DetalleOrden $detalle, Orden $orden): array
    {
        $combustible = $detalle->combustible_precio
            ?: CombustibleEstimator::clavePrecio($detalle->tipo_combustible);
        $ciudad = CombustibleEstimator::ciudad($detalle->ciudad_precio);
        $estimacion = CombustibleEstimator::calcular(
            (float) $detalle->monto,
            $combustible,
            $detalle->vehiculo,
            $ciudad,
            $orden->fecha_asignacion
        );
        $galones = $detalle->galones_estimados ?? $estimacion['galones_estimados'] ?? null;

        return [
            'combustible_precio' => $detalle->combustible_precio
                ?? $estimacion['combustible_precio']
                ?? $combustible,
            'ciudad_precio' => $detalle->ciudad_precio
                ?? $estimacion['ciudad_precio']
                ?? $ciudad,
            'precio_galon' => $detalle->precio_galon
                ?? $estimacion['precio_galon']
                ?? null,
            'precio_vigente_desde' => $detalle->precio_vigente_desde?->toDateString()
                ?? $estimacion['precio_vigente_desde']
                ?? null,
            'galones_estimados' => $galones,
            'litros_estimados' => $estimacion['litros_estimados']
                ?? ($galones === null ? null : round((float) $galones * CombustibleEstimator::LITROS_POR_GALON, 2)),
            'km_estimados' => $detalle->km_estimados
                ?? $estimacion['km_estimados']
                ?? null,
        ];
    }

    private function resumenCombustible($grupo, string $tipoCombustible): array
    {
        $monto = (float) $grupo->sum(fn (array $linea) => (float) $linea['detalle']->monto);
        $lineasConPrecio = $grupo->filter(fn (array $linea) => $linea['galones_estimados'] !== null);
        $galones = (float) $lineasConPrecio->sum(fn (array $linea) => $linea['galones_estimados']);
        $montoConPrecio = (float) $lineasConPrecio->sum(fn (array $linea) => (float) $linea['detalle']->monto);

        return [
            'tipo_combustible' => $tipoCombustible,
            'monto_total' => round($monto, 2),
            'galones_total' => round($galones, 2),
            'litros_total' => round($galones * 3.785411784, 2),
            'km_total' => round((float) $lineasConPrecio->sum(fn (array $linea) => $linea['km_estimados'] ?? 0), 2),
            'precio_promedio_galon' => $galones ? round($montoConPrecio / $galones, 2) : null,
            'cantidad_ordenes' => $grupo->pluck('orden')->unique('id')->count(),
        ];
    }

    private function porMes($lineas): array
    {
        return $lineas
            ->groupBy(fn (array $linea) => $linea['orden']->fecha_completada?->format('Y-m'))
            ->map(fn ($grupo, $mes) => [
                'mes' => $mes,
                'monto_total' => round((float) $grupo->sum(fn (array $linea) => (float) $linea['detalle']->monto), 2),
                'cantidad_ordenes' => $grupo->pluck('orden')->unique('id')->count(),
            ] + $this->consumo($grupo))
            ->sortKeys()
            ->values()
            ->all();
    }

    private function porVehiculo($lineas): array
    {
        return $lineas
            ->filter(fn (array $linea) => $linea['detalle']->tipo_destino === 'vehiculo' && $linea['detalle']->vehiculo_id !== null)
            ->groupBy(fn (array $linea) => $linea['detalle']->vehiculo_id)
            ->map(fn ($grupo, $id) => [
                'id' => (int) $id,
                'placa' => $grupo->first()['detalle']->vehiculo?->placa ?? 'Vehículo',
                'descripcion' => trim(
                    ($grupo->first()['detalle']->vehiculo?->marca ?? '')
                    .' '.($grupo->first()['detalle']->vehiculo?->modelo ?? '')
                ),
                'rendimiento_km_litro' => $grupo->first()['detalle']->vehiculo
                    ? (float) $grupo->first()['detalle']->vehiculo->rendimiento_km_litro
                    : null,
                'monto_total' => round((float) $grupo->sum(fn (array $linea) => (float) $linea['detalle']->monto), 2),
                'cantidad_ordenes' => $grupo->pluck('orden')->unique('id')->count(),
            ] + $this->consumo($grupo))
            ->sortByDesc('monto_total')
            ->values()
            ->all();
    }

    private function consumo($lineas): array
    {
        $conPrecio = $lineas->filter(fn (array $linea) => $linea['galones_estimados'] !== null);
        $galones = (float) $conPrecio->sum(fn (array $linea) => $linea['galones_estimados']);

        return [
            'galones_total' => round($galones, 2),
            'km_total' => round((float) $conPrecio->sum(fn (array $linea) => $linea['km_estimados'] ?? 0), 2),
            'precio_promedio_galon' => $galones > 0
                ? round((float) $conPrecio->sum(fn (array $linea) => (float) $linea['detalle']->monto) / $galones, 2)
                : null,
        ];
    }

    private function rankingLineas($lineas, callable $id, callable $nombre): array
    {
        return $lineas
            ->groupBy($id)
            ->map(fn ($grupo, $key) => [
                'id' => (int) $key,
                'nombre' => $nombre($grupo->first()),
                'monto_total' => round((float) $grupo->sum(fn (array $linea) => (float) $linea['detalle']->monto), 2),
                'cantidad_ordenes' => $grupo->pluck('orden')->unique('id')->count(),
            ] + $this->consumo($grupo))
            ->sortByDesc('monto_total')
            ->values()
            ->all();
    }

    private function adjuntarEstimaciones($ordenes): void
    {
        foreach ($ordenes as $orden) {
            foreach ($orden->detalles as $detalle) {
                foreach ($this->calcularEstimacion($detalle, $orden) as $campo => $valor) {
                    $detalle->setAttribute($campo, $valor);
                }
            }
        }
    }

    private function describirFiltros(array $filtros): array
    {
        $descripcion = [];
        $etiquetasEstados = [
            'pendiente' => 'Pendiente',
            'abierta' => 'Abierta',
            'abierta_tarde' => 'Abierta tarde',
            'completada' => 'Completada',
            'completada_tarde' => 'Completada tarde',
            'vencida' => 'Vencida',
            'cancelada' => 'Cancelada',
        ];

        foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $campo => $etiqueta) {
            if (isset($filtros[$campo])) {
                $descripcion[$etiqueta] = $filtros[$campo];
            }
        }

        if (isset($filtros['estado'])) {
            $descripcion['Estado'] = $etiquetasEstados[$filtros['estado']];
        }

        if (isset($filtros['vehiculo_id'])) {
            $descripcion['Vehículo'] = Vehiculo::findOrFail($filtros['vehiculo_id'])->placa;
        }

        if (isset($filtros['conductor_id'])) {
            $conductor = Conductor::with('usuario:id,name')->findOrFail($filtros['conductor_id']);
            $descripcion['Conductor'] = $conductor->usuario->name;
        }

        if (isset($filtros['gasolinera_id'])) {
            $descripcion['Gasolinera'] = Gasolinera::findOrFail($filtros['gasolinera_id'])->nombre;
        }

        return $descripcion;
    }
}
