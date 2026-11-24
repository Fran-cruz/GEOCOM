<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $usuario
                    ? [
                        'id' => $usuario->id,
                        'name' => $usuario->name,
                        'email' => $usuario->email,
                        'rol' => $usuario->roles()
                            ->wherePivot('activo', true)
                            ->value('nombre'),
                    ]
                    : null,
                'can_operate' => $usuario?->can('web_operativo') ?? false,
                'can_consult' => $usuario?->can('web_consulta') ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
