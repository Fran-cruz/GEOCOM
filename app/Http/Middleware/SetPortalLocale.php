<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetPortalLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = App::currentLocale();
        App::setLocale('es');

        try {
            return $next($request);
        } finally {
            App::setLocale($locale);
        }
    }
}
