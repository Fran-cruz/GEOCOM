<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehiculoWebController extends Controller
{
    use ValidatesWebRequests;

    public function index()
    {
        return Inertia::render('Vehiculos/Index', [
            'vehiculos' => Vehiculo::orderBy('placa')->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'placa' => ['required', 'string', 'max:15', 'unique:vehiculos,placa'],
            'marca' => ['required', 'string', 'max:50'],
            'modelo' => ['required', 'string', 'max:80'],
            'anio' => ['nullable', 'integer', 'between:1980,2100'],
            'tipo_combustible' => ['sometimes', 'in:diesel,gasolina'],
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.unique' => 'La placa ya está registrada.',
            'marca.required' => 'La marca es obligatoria.',
            'modelo.required' => 'El modelo es obligatorio.',
            'anio.between' => 'El año debe estar entre 1980 y 2100.',
            'tipo_combustible.in' => 'El combustible debe ser diésel o gasolina.',
        ]);

        Vehiculo::create($datos);

        return back()->with('success', 'El vehículo fue creado correctamente.');
    }

    public function update(Request $request, string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);
        $datos = $this->validateWeb($request, [
            'placa' => ['sometimes', 'required', 'string', 'max:15', Rule::unique('vehiculos', 'placa')->ignore($vehiculo->id)],
            'marca' => ['sometimes', 'required', 'string', 'max:50'],
            'modelo' => ['sometimes', 'required', 'string', 'max:80'],
            'anio' => ['nullable', 'integer', 'between:1980,2100'],
            'tipo_combustible' => ['sometimes', 'in:diesel,gasolina'],
            'activo' => ['sometimes', 'boolean'],
        ], [
            'placa.unique' => 'La placa ya está registrada.',
            'anio.between' => 'El año debe estar entre 1980 y 2100.',
            'tipo_combustible.in' => 'El combustible debe ser diésel o gasolina.',
        ]);

        $vehiculo->update($datos);

        return back()->with('success', 'El vehículo fue actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        Vehiculo::findOrFail($id)->update(['activo' => false]);

        return back()->with('success', 'El vehículo fue desactivado.');
    }
}
