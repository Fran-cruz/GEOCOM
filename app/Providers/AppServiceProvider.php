<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cada Gate recibe el usuario autenticado y devuelve true (pasa) o false (403)
        Gate::define('admin',        fn (User $usuario) => $usuario->tieneRol('admin'));
        Gate::define('conductor',    fn (User $usuario) => $usuario->tieneRol('conductor'));
        Gate::define('contabilidad', fn (User $usuario) => $usuario->tieneRol('contabilidad'));
        Gate::define('supervisor',   fn (User $usuario) => $usuario->tieneRol('supervisor'));
        // 'consulta' = solo lectura: admin, supervisor o contabilidad
        Gate::define('consulta',     fn (User $usuario) => $usuario->tieneRol('admin') || $usuario->tieneRol('supervisor') || $usuario->tieneRol('contabilidad'));
        Gate::define('web_operativo', fn (User $usuario) => $usuario->tieneRol('admin'));
        Gate::define('web_consulta', fn (User $usuario) => $usuario->tieneRol('admin') || $usuario->tieneRol('supervisor') || $usuario->tieneRol('contabilidad'));
    }
}