<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
            // Opcional: si no viene, la base usa 8.00 km/litro
            'rendimiento_km_litro' => 'sometimes|numeric|between:0.01,99.99',
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
            'rendimiento_km_litro' => 'sometimes|numeric|between:0.01,99.99',
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

    // Sube o reemplaza la foto del vehículo (multipart, campo "foto").
    // Si ya tenía una, se borra el archivo anterior.
    public function subirFoto(Request $request, string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $anterior = $vehiculo->foto_ruta;

        $vehiculo->foto_ruta = $request->file('foto')->store("vehiculos/{$vehiculo->id}", 'local');
        $vehiculo->save();

        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return $vehiculo->fresh();
    }

    // Quita la foto: borra el archivo y deja foto_ruta en null
    public function quitarFoto(string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        if ($vehiculo->foto_ruta) {
            Storage::disk('local')->delete($vehiculo->foto_ruta);
            $vehiculo->foto_ruta = null;
            $vehiculo->save();
        }

        return $vehiculo->fresh();
    }

    // Sirve la foto desde el disco privado a cualquier usuario con token válido
    public function verFoto(string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        abort_unless(
            $vehiculo->foto_ruta && Storage::disk('local')->exists($vehiculo->foto_ruta),
            404
        );

        return Storage::disk('local')->response($vehiculo->foto_ruta, null, [
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
