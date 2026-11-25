<?php

namespace App\Http\Controllers;

use App\Models\Gasolinera;
use App\Models\Solicitud;
use App\Models\Vehiculo;
use App\Http\Controllers\Concerns\ValidatesWebRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SolicitudWebController extends Controller
{
    use ValidatesWebRequests;

    public function index(Request $request)
    {
        $filtros = $this->validateWeb($request, [
            'estado' => ['nullable', 'in:pendiente,aprobada,rechazada'],
        ], [
            'estado.in' => 'El estado seleccionado no es válido.',
        ]);

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => Solicitud::with(['conductor.usuario:id,name,email', 'vehiculo'])
                ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filtros' => $filtros,
            'gasolineras' => Gasolinera::where('activo', true)->orderBy('nombre')->get(),
            'vehiculos' => Vehiculo::where('activo', true)->orderBy('placa')->get(),
        ]);
    }

    public function rechazar(string $id)
    {
        $solicitud = Solicitud::findOrFail($id);
        abort_unless($solicitud->estado === 'pendiente', 422, 'Solo se pueden rechazar solicitudes pendientes.');
        $solicitud->update(['estado' => 'rechazada']);

        return back()->with('success', 'La solicitud fue rechazada.');
    }
}
