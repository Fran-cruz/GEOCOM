<?php

namespace App\Console\Commands;

use App\Models\DetalleOrden;
use App\Models\Evidencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportarEvidenciasLegacy extends Command
{
    // Uso: php artisan evidencias:importar-legacy --tipo=bomba
    protected $signature = 'evidencias:importar-legacy
                            {--tipo=bomba : Tipo a asignar a las fotos viejas}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Pasa las fotos viejas (ruta_evidencia) a la tabla evidencias';

    public function handle(): int
    {
        $tipo = $this->option('tipo');

        if (! in_array($tipo, Evidencia::TIPOS, true)) {
            $this->error('Tipo inválido. Use: ' . implode(', ', Evidencia::TIPOS));
            return self::FAILURE;
        }

        $detalles = DetalleOrden::whereNotNull('ruta_evidencia')
            ->whereDoesntHave('evidencias') // si ya tiene evidencias nuevas, no duplicar
            ->get();

        $privado = Storage::disk('local');
        $publico = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        $importadas = 0;
        $movidas = 0;
        $faltantes = 0;

        foreach ($detalles as $detalle) {
            $ruta = $detalle->ruta_evidencia;

            // Si el archivo quedó en el disco público, lo pasamos al privado
            if (! $privado->exists($ruta)) {
                if ($publico->exists($ruta)) {
                    if (! $dryRun) {
                        $privado->makeDirectory(dirname($ruta));
                        $privado->writeStream($ruta, $publico->readStream($ruta));
                        $publico->delete($ruta);
                    }
                    $movidas++;
                } else {
                    $faltantes++;
                    $this->warn("Falta el archivo: {$ruta} (detalle {$detalle->id})");
                    continue;
                }
            }

            if (! $dryRun) {
                $detalle->evidencias()->create([
                    'tipo'         => $tipo,
                    'ruta'         => $ruta,
                    'fecha_subida' => $detalle->fecha_evidencia ?? now(),
                ]);
            }

            $importadas++;
        }

        $this->newLine();
        $this->info("Importadas: {$importadas} | movidas de public: {$movidas} | archivos faltantes: {$faltantes}");

        if ($dryRun) {
            $this->comment('Dry run: no se escribió nada en la base ni en el disco.');
        }

        return self::SUCCESS;
    }
}
