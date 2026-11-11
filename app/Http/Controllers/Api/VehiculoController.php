<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehiculoController extends Controller
{
    // Solo vehículos activos, ordenados por placa
    public function index()
    {
        return Vehiculo::where('activo', true)->orderBy('placa')->paginate(30);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'placa'            => 'required|string|max:15|unique:vehiculos,placa',
            'marca'            => 'required|string|max:50',
            'modelo'           => 'required|string|max:80',
            'anio'             => 'nullable|integer|between:1980,2100',
            'tipo_combustible' => 'sometimes|in:diesel,gasolina',
        ]);

        return response()->json(Vehiculo::create($datos), 201);
    }

    public function show(string $id)
    {
        return Vehiculo::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        $datos = $request->validate([
            // ignore(): permite conservar su propia placa sin error de "ya existe"
            'placa'            => ['sometimes', 'string', 'max:15', Rule::unique('vehiculos', 'placa')->ignore($vehiculo->id)],
            'marca'            => 'sometimes|string|max:50',
            'modelo'           => 'sometimes|string|max:80',
            'anio'             => 'nullable|integer|between:1980,2100',
            'tipo_combustible' => 'sometimes|in:diesel,gasolina',
            'activo'           => 'sometimes|boolean',
        ]);

        $vehiculo->update($datos);

        return $vehiculo;
    }

    // No se borra: se desactiva para conservar el historial de órdenes
    public function destroy(string $id)
    {
        Vehiculo::findOrFail($id)->update(['activo' => false]);

        return response()->noContent();
    }
}