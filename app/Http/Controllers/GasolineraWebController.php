<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Models\Gasolinera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GasolineraWebController extends Controller
{
    use ValidatesWebRequests;

    public function index()
    {
        return Inertia::render('Gasolineras/Index', [
            'gasolineras' => Gasolinera::orderBy('nombre')->paginate(30),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'codigo_qr' => ['required', 'string', 'max:100', 'unique:gasolineras,codigo_qr'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'codigo_qr.required' => 'El código QR es obligatorio.',
            'codigo_qr.unique' => 'El código QR ya está registrado.',
        ]);

        Gasolinera::create($datos);

        return back()->with('success', 'La gasolinera fue creada correctamente.');
    }

    public function update(Request $request, string $id)
    {
        $gasolinera = Gasolinera::findOrFail($id);
        $datos = $this->validateWeb($request, [
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'codigo_qr' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('gasolineras', 'codigo_qr')->ignore($gasolinera->id)],
            'activo' => ['sometimes', 'boolean'],
        ], [
            'codigo_qr.unique' => 'El código QR ya está registrado.',
        ]);

        $gasolinera->update($datos);

        return back()->with('success', 'La gasolinera fue actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        Gasolinera::findOrFail($id)->update(['activo' => false]);

        return back()->with('success', 'La gasolinera fue desactivada.');
    }
}
