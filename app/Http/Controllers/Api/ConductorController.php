<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Conductor, Rol, User};
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConductorController extends Controller
{
    // Lista paginada; with() carga el usuario en la misma consulta (evita N+1)
    public function index()
    {
        return Conductor::with('usuario:id,name,email')->latest()->paginate(15);
    }

    // Crea el usuario, le asigna el rol conductor y crea su ficha, todo en una transacción:
    // si algo falla, no queda nada a medias
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'     => 'required|string|max:255',
            'correo'     => 'required|email|unique:users,email',
            'contrasena' => 'required|string|min:8',
            'telefono'   => 'required|string|max:20',
            'cargo'      => 'required|string|max:100',
        ]);

        $conductor = DB::transaction(function () use ($datos) {
            // El password se cifra solo gracias al cast "hashed" del User
            $usuario = User::create([
                'name'     => $datos['nombre'],
                'email'    => $datos['correo'],
                'password' => $datos['contrasena'],
            ]);

            $usuario->roles()->attach(Rol::where('nombre', 'conductor')->value('id'));

            return Conductor::create([
                'usuario_id' => $usuario->id,
                'telefono'   => $datos['telefono'],
                'cargo'      => $datos['cargo'],
            ]);
        });

        return response()->json($conductor->load('usuario:id,name,email'), 201);
    }

    public function show(string $id)
    {
        return Conductor::with('usuario:id,name,email')->findOrFail($id);
    }

    // Actualiza datos del usuario (nombre, correo) y de la ficha (teléfono, cargo, activo)
    public function update(Request $request, string $id)
    {
        $conductor = Conductor::findOrFail($id);

        $datos = $request->validate([
            'nombre'   => 'sometimes|string|max:255',
            // ignore(): permite conservar su propio correo sin error de "ya existe"
            'correo'   => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($conductor->usuario_id)],
            'telefono' => 'sometimes|string|max:20',
            'cargo'    => 'sometimes|string|max:100',
            'activo'   => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($conductor, $datos) {
            // array_filter descarta los null, así solo se actualiza lo que se envió
            $conductor->usuario->update(array_filter([
                'name'  => $datos['nombre'] ?? null,
                'email' => $datos['correo'] ?? null,
            ]));
            $conductor->update(Arr::only($datos, ['telefono', 'cargo', 'activo']));
        });

        return $conductor->fresh('usuario');
    }

    // No se borra: se desactiva para conservar el historial de órdenes
    public function destroy(string $id)
    {
        Conductor::findOrFail($id)->update(['activo' => false]);

        return response()->noContent();
    }
}