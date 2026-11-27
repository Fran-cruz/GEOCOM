<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combustible;
use App\Services\CombustibleHistoryService;
use Illuminate\Http\Request;

class CombustibleController extends Controller
{
    public function __construct(private CombustibleHistoryService $historial)
    {
    }

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

    public function historial(Request $request)
    {
        $datos = $request->validate([
            'ciudad' => 'nullable|in:tegucigalpa,san_pedro_sula',
            'semanas' => 'nullable|integer|between:1,52',
        ]);

        return $this->historial->obtener(
            $datos['ciudad'] ?? null,
            (int) ($datos['semanas'] ?? 12)
        );
    }
}
