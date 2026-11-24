<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthWebController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if (! Auth::attempt($datos, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $usuario = $request->user();

        if (
            ! $usuario->tieneRol('admin')
            && ! $usuario->tieneRol('supervisor')
            && ! $usuario->tieneRol('contabilidad')
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'No tienes un rol activo autorizado para el portal web.'])->onlyInput('email');
        }

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
