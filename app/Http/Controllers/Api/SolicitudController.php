<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use Illuminate\Http\Request;

class SolicitudController extends Controller
{
    public function index(Request $request)
    {
        $consulta = Solicitud::with(['conductor.usuario:id,name', 'vehiculo'])->latest();

        // Si quien consulta es conductor, solo ve las suyas
        if ($conductor = $request->user()->conductor) {
            $consulta->where('conductor_id', $conductor->id);
        }

        return $consulta->paginate(15);
    }

    // El conductor crea una solicitud de combustible
    public function store(Request $request)
    {
        $conductor = $request->user()->conductor;
        abort_unless($conductor && $conductor->activo, 403, 'Solo un conductor activo puede generar solicitudes.');

        $datos = $request->validate([
            'tipo_destino'          => 'required|in:vehiculo,mgp,ambos',
            // required_if: vehículo obligatorio si el destino incluye vehículo; MGP igual con su descripción
            'vehiculo_id'           => 'required_if:tipo_destino,vehiculo,ambos|nullable|exists:vehiculos,id',
            'descripcion_mgp'       => 'required_if:tipo_destino,mgp,ambos|nullable|string|max:150',
            'descripcion_actividad' => 'required|string|max:1000',
        ]);

        $solicitud = Solicitud::create($datos + ['conductor_id' => $conductor->id]);

        return response()->json($solicitud->load('vehiculo'), 201);
    }

    public function show(Request $request, string $id)
    {
        $solicitud = Solicitud::findOrFail($id);

        // Un conductor no puede ver solicitudes de otro
        $conductor = $request->user()->conductor;
        abort_if($conductor && $solicitud->conductor_id !== $conductor->id, 403);

        return $solicitud->load('conductor.usuario:id,name', 'vehiculo', 'orden');
    }
}