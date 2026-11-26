<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Models\CajaChica;
use App\Models\Conductor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CajaChicaWebController extends Controller
{
    use ValidatesWebRequests;

    public function index()
    {
        return Inertia::render('CajaChica/Index', [
            'movimientos' => CajaChica::with('conductor.usuario:id,name', 'registrador:id,name')
                ->latest('fecha_entrega')
                ->paginate(15),
            'conductores' => Conductor::with('usuario:id,name')
                ->where('activo', true)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'conductor_id' => ['nullable', 'exists:conductores,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_entrega' => ['required', 'date'],
        ], [
            'conductor_id.exists' => 'El conductor seleccionado no existe.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.min' => 'El monto debe ser mayor que cero.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'fecha_entrega.required' => 'La fecha de entrega es obligatoria.',
            'fecha_entrega.date' => 'La fecha de entrega no es válida.',
        ]);

        CajaChica::create($datos + ['registrado_por' => $request->user()->id]);

        return back()->with('success', 'La entrega de caja chica fue registrada.');
    }
}
