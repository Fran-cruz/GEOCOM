<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizarEvidencias extends Command
{
    // Uso: php artisan evidencias:privar
    protected $signature = 'evidencias:privar';

    protected $description = 'Mueve las fotos de evidencia del disco público al privado';

    public function handle(): int
    {
        $publico = Storage::disk('public');
        $privado = Storage::disk('local');

        if (! $publico->exists('evidencias')) {
            $this->info('No hay evidencias en el disco público. Nada que mover.');
            return self::SUCCESS;
        }

        $archivos = $publico->allFiles('evidencias');
        $barra = $this->output->createProgressBar(count($archivos));

        foreach ($archivos as $archivo) {
            $privado->makeDirectory(dirname($archivo));

            // readStream() no carga el archivo completo en memoria
            if ($privado->writeStream($archivo, $publico->readStream($archivo)) !== false) {
                $publico->delete($archivo); // ya está a salvo en privado
            } else {
                $this->newLine();
                $this->error("No se pudo mover: {$archivo}");
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);
        $this->info(count($archivos) . ' evidencias movidas a storage/app/private/evidencias');

        return self::SUCCESS;
    }
}
