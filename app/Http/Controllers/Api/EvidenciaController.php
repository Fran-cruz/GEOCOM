<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidencia;
use Illuminate\Support\Facades\Storage;

class EvidenciaController extends Controller
{
    // Sirve la foto desde el disco privado; solo admin/contabilidad con token válido
    public function ver(string $id)
    {
        $evidencia = Evidencia::findOrFail($id);

        abort_unless(Storage::disk('local')->exists($evidencia->ruta), 404);

        return Storage::disk('local')->response($evidencia->ruta, null, [
            'Cache-Control'          => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
