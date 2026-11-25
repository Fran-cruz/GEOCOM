<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Rol;
use App\Models\User;
use App\Http\Controllers\Concerns\ValidatesWebRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConductorWebController extends Controller
{
    use ValidatesWebRequests;

    public function index()
    {
        return Inertia::render('Conductores/Index', [
            'conductores' => Conductor::with('usuario:id,name,email')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contrasena' => ['required', 'string', 'min:8'],
            'telefono' => ['required', 'string', 'max:20'],
            'cargo' => ['required', 'string', 'max:100'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'Ingresa un correo válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'contrasena.required' => 'La contraseña es obligatoria.',
            'contrasena.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'cargo.required' => 'El cargo es obligatorio.',
        ]);

        $conductor = DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['correo'],
                'password' => $datos['contrasena'],
            ]);
            $usuario->roles()->attach(Rol::where('nombre', 'conductor')->firstOrFail()->id);

            return Conductor::create([
                'usuario_id' => $usuario->id,
                'telefono' => $datos['telefono'],
                'cargo' => $datos['cargo'],
            ]);
        });

        return back()->with('success', 'El conductor fue creado correctamente.');
    }

    public function update(Request $request, string $id)
    {
        $conductor = Conductor::with('usuario')->findOrFail($id);
        $datos = $this->validateWeb($request, [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'correo' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($conductor->usuario_id)],
            'contrasena' => ['sometimes', 'nullable', 'string', 'min:8'],
            'telefono' => ['sometimes', 'required', 'string', 'max:20'],
            'cargo' => ['sometimes', 'required', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ], [
            'correo.email' => 'Ingresa un correo válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'contrasena.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        DB::transaction(function () use ($conductor, $datos) {
            $usuarioDatos = Arr::only($datos, ['nombre', 'correo', 'contrasena']);
            $conductor->usuario->update(array_filter([
                'name' => $usuarioDatos['nombre'] ?? null,
                'email' => $usuarioDatos['correo'] ?? null,
                'password' => filled($usuarioDatos['contrasena'] ?? null) ? $usuarioDatos['contrasena'] : null,
            ], fn ($value) => $value !== null));
            $conductor->update(Arr::only($datos, ['telefono', 'cargo', 'activo']));
        });

        return back()->with('success', 'El conductor fue actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $conductor = Conductor::with('usuario.roles')->findOrFail($id);

        DB::transaction(function () use ($conductor) {
            $conductor->update(['activo' => false]);
            $rol = $conductor->usuario->roles->firstWhere('nombre', 'conductor');
            if ($rol) {
                $conductor->usuario->roles()->updateExistingPivot($rol->id, ['activo' => false]);
            }
        });

        return back()->with('success', 'El conductor fue desactivado.');
    }
}
