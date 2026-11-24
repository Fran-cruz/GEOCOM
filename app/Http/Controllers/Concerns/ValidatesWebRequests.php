<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ValidatesWebRequests
{
    protected function validateWeb(Request $request, array $rules, array $messages = []): array
    {
        $locale = app()->getLocale();
        app()->setLocale('es');

        try {
            return $request->validate($rules, $messages);
        } finally {
            app()->setLocale($locale);
        }
    }
}
