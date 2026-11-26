<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Models\Conductor;
use App\Models\DetalleOrden;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $conteos = $this->ordenesFiltradas($filtros)
            ->select('ordenes.estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('ordenes.estado')
            ->pluck('cantidad', 'estado')
            ->map(fn ($cantidad) => (int) $cantidad)
            ->all();
        $porEstado = array_replace(array_fill_keys(self::ESTADOS_ORDEN, 0), $conteos);
        $consumo = fn () => $this->consumosFiltrados($filtros);

        return [
            'resumen' => [
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
                'ordenes_ejecutadas' => $consumo()->distinct()->count('ordenes.id'),
                'monto_consumido' => (float) $consumo()->sum('detalle_ordenes.monto'),
            ],
            'porConductor' => $consumo()
                ->join('conductores', 'conductores.id', '=', 'ordenes.conductor_id')
                ->join('users', 'users.id', '=', 'conductores.usuario_id')
                ->groupBy('conductores.id', 'users.name')
                ->selectRaw('conductores.id as conductor_id, users.name as nombre_conductor,
                    COUNT(DISTINCT ordenes.id) as cantidad_ordenes, SUM(detalle_ordenes.monto) as monto_total')
                ->get(),
            'porGasolinera' => $consumo()
                ->join('gasolineras', 'gasolineras.id', '=', 'ordenes.gasolinera_id')
                ->groupBy('gasolineras.id', 'gasolineras.nombre')
                ->selectRaw('gasolineras.id as gasolinera_id, gasolineras.nombre as nombre_gasolinera,
                    COUNT(DISTINCT ordenes.id) as cantidad_ordenes, SUM(detalle_ordenes.monto) as monto_total')
                ->get(),
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

    private function consumosFiltrados(array $filtros)
    {
        $ordenes = Orden::query()
            ->whereIn('estado', self::ESTADOS_EJECUTADOS)
            ->when($filtros['desde'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_completada', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_completada', '<=', $fecha))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filtros['conductor_id'] ?? null, fn ($query, $id) => $query->where('conductor_id', $id))
            ->when($filtros['gasolinera_id'] ?? null, fn ($query, $id) => $query->where('gasolinera_id', $id))
            ->when($filtros['vehiculo_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'detalles',
                fn ($detalle) => $detalle->where('vehiculo_id', $id)
            ))
            ->select('ordenes.id');

        return DetalleOrden::query()
            ->join('ordenes', 'ordenes.id', '=', 'detalle_ordenes.orden_id')
            ->whereIn('ordenes.id', $ordenes)
            ->whereIn('ordenes.estado', self::ESTADOS_EJECUTADOS)
            ->when($filtros['vehiculo_id'] ?? null, fn ($query, $id) => $query->where('detalle_ordenes.vehiculo_id', $id));
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
