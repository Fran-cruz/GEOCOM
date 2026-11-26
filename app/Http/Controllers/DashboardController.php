<?php

namespace App\Http\Controllers;

use App\Models\CajaChica;
use App\Models\Orden;
use App\Models\Solicitud;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
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

    public function index()
    {
        Orden::marcarVencidas();

        $ordenesPorEstado = Orden::query()
            ->select('estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(fn ($cantidad) => (int) $cantidad)
            ->all();
        $ordenesPorEstado = array_replace(array_fill_keys(self::ESTADOS_ORDEN, 0), $ordenesPorEstado);
        $ordenesEjecutadas = array_sum(array_intersect_key(
            $ordenesPorEstado,
            array_flip(self::ESTADOS_EJECUTADOS)
        ));
        $gastoEjecutado = (float) DB::table('detalle_ordenes')
            ->join('ordenes', 'ordenes.id', '=', 'detalle_ordenes.orden_id')
            ->whereIn('ordenes.estado', self::ESTADOS_EJECUTADOS)
            ->sum('detalle_ordenes.monto');

        return Inertia::render('Dashboard/Index', [
            'solicitudesPendientes' => Solicitud::where('estado', 'pendiente')->count(),
            'ordenesPorEstado' => $ordenesPorEstado,
            'ordenesEjecutadas' => $ordenesEjecutadas,
            'gastoEjecutado' => $gastoEjecutado,
            'gastoCompletado' => $gastoEjecutado,
            'totalCajaChica' => (float) CajaChica::sum('monto'),
            'ultimasSolicitudes' => Solicitud::with(['conductor.usuario:id,name', 'vehiculo'])
                ->where('estado', 'pendiente')
                ->latest()
                ->limit(5)
                ->get(),
            'ultimasOrdenes' => Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo'])
                ->latest('fecha_asignacion')
                ->limit(5)
                ->get(),
        ]);
    }
}
