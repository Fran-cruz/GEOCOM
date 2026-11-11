<?php

namespace App\Console\Commands;

use App\Models\Orden;
use Illuminate\Console\Command;

class MarcarOrdenesVencidas extends Command
{
    // Nombre con el que se invoca: php artisan ordenes:vencer
    protected $signature = 'ordenes:vencer';

    protected $description = 'Marca las órdenes vencidas y avisa por correo';

    public function handle(): int
    {
        // Toda la lógica vive en el modelo, así el comando y las consultas usan lo mismo
        Orden::marcarVencidas();
        $this->info('Órdenes vencidas revisadas.');

        return self::SUCCESS;
    }
}