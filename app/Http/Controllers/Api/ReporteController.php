<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleOrden;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    // Total gastado y cantidad de órdenes completadas por conductor
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

    // Lo mismo, agrupado por gasolinera
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

    // Base común: solo órdenes completadas, filtradas por rango de fecha de cierre (?desde=&hasta=)
    private function base(Request $request)
    {
        return DetalleOrden::query()
            ->join('ordenes', 'ordenes.id', '=', 'detalle_ordenes.orden_id')
            ->where('ordenes.estado', 'completada')
            ->when($request->desde, fn ($q, $v) => $q->whereDate('ordenes.fecha_completada', '>=', $v))
            ->when($request->hasta, fn ($q, $v) => $q->whereDate('ordenes.fecha_completada', '<=', $v));
    }
}