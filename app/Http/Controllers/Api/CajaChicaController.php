<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CajaChica;
use Illuminate\Http\Request;

class CajaChicaController extends Controller
{
    // Más recientes primero, con el nombre del conductor
    public function index()
    {
        return CajaChica::with('conductor.usuario:id,name')->latest('fecha_entrega')->paginate(15);
    }

    // El admin registra una entrega; "registrado_por" se toma del token, no del cliente
    public function store(Request $request)
    {
        $datos = $request->validate([
            'conductor_id'  => 'nullable|exists:conductores,id',
            'monto'         => 'required|numeric|min:0.01',
            'descripcion'   => 'required|string|max:255',
            'fecha_entrega' => 'required|date',
        ]);

        $registro = CajaChica::create($datos + ['registrado_por' => $request->user()->id]);

        return response()->json($registro, 201);
    }

    // Detalle de un registro, con el conductor y quién lo registró
    public function show(string $id)
    {
        return CajaChica::with('conductor.usuario:id,name', 'registrador:id,name')->findOrFail($id);
    }
}