<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combustible;
use Illuminate\Http\Request;

class CombustibleController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate([
            'ciudad' => 'nullable|in:tegucigalpa,san_pedro_sula',
        ]);

        return Combustible::vigentes()
            ->when($datos['ciudad'] ?? null, fn ($q, $ciudad) => $q->where('ciudad', $ciudad))
            ->orderBy('ciudad')
            ->orderBy('combustible')
            ->get();
    }
}
