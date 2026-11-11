<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login: devuelve el token de Sanctum que la app Kotlin enviará en cada petición
    public function login(Request $request)
    {
        $datos = $request->validate([
            'correo'             => 'required|email',
            'contrasena'         => 'required|string',
            'nombre_dispositivo' => 'nullable|string|max:100', // para identificar el token
        ]);

        $usuario = User::where('email', $datos['correo'])->first();

        // Mismo mensaje para correo o contraseña mal, para no revelar qué falló
        if (! $usuario || ! Hash::check($datos['contrasena'], $usuario->password)) {
            return response()->json(['mensaje' => 'Correo o contraseña incorrectos.'], 401);
        }

        // Debe tener un rol activo; si es conductor, su ficha también debe estar activa
        $rol = $usuario->roles()->wherePivot('activo', true)->first();
        if (! $rol || ($rol->nombre === 'conductor' && ! $usuario->conductor?->activo)) {
            return response()->json(['mensaje' => 'Tu cuenta está desactivada o no tiene un rol asignado.'], 403);
        }

        $token = $usuario->createToken($datos['nombre_dispositivo'] ?? 'app-movil')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'usuario' => [
                'id'     => $usuario->id,
                'nombre' => $usuario->name,
                'correo' => $usuario->email,
                'rol'    => $rol->nombre, // admin, contabilidad o conductor
            ],
        ]);
    }

    // Logout: elimina solo el token con el que se hizo la petición
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    // Para que la app sepa quién es el usuario y qué rol tiene al reabrirse
    public function yo(Request $request)
    {
        $usuario = $request->user();
        $rol = $usuario->roles()->wherePivot('activo', true)->first();

        return [
            'id'     => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'rol'    => $rol?->nombre,
        ];
    }
}