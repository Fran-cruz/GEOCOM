<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasolinera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GasolineraController extends Controller
{
    public function index()
    {
        return Gasolinera::orderBy('nombre')->paginate(30);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'    => 'required|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'codigo_qr' => 'required|string|max:100|unique:gasolineras,codigo_qr', // texto del QR físico
        ]);

        return response()->json(Gasolinera::create($datos), 201);
    }

    public function show(string $id)
    {
        return Gasolinera::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $gasolinera = Gasolinera::findOrFail($id);

        $datos = $request->validate([
            'nombre'    => 'sometimes|string|max:100',
            'direccion' => 'nullable|string|max:255',
            // ignore(): permite conservar su propio código sin error de "ya existe"
            'codigo_qr' => ['sometimes', 'string', 'max:100', Rule::unique('gasolineras', 'codigo_qr')->ignore($gasolinera->id)],
            'activo'    => 'sometimes|boolean',
        ]);

        $gasolinera->update($datos);

        return $gasolinera;
    }

    // No se borra: se desactiva para conservar el historial de órdenes
    public function destroy(string $id)
    {
        Gasolinera::findOrFail($id)->update(['activo' => false]);

        return response()->noContent();
    }
}