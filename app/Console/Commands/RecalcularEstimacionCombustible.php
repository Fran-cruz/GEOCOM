<?php

namespace App\Console\Commands;

use App\Models\DetalleOrden;
use App\Support\EstimadorCombustible;
use Illuminate\Console\Command;

// Llena precio, galones y km de las líneas creadas antes de esta función,
// usando el precio que regía en la fecha de asignación de cada orden.
class RecalcularEstimacionCombustible extends Command
{
    protected $signature = 'combustible:recalcular {--todas : Recalcula también las líneas que ya tienen precio}';

    protected $description = 'Calcula precio del galón, galones y km estimados de las líneas de órdenes';

    public function handle(): int
    {
        $consulta = DetalleOrden::with(['orden', 'vehiculo'])
            ->when(! $this->option('todas'), fn ($q) => $q->whereNull('precio_galon'));

        $actualizadas = 0;
        $sinPrecio = 0;

        $consulta->chunkById(200, function ($lineas) use (&$actualizadas, &$sinPrecio) {
            foreach ($lineas as $linea) {
                $estimacion = EstimadorCombustible::calcular(
                    (float) $linea->monto,
                    EstimadorCombustible::clavePrecio($linea->tipo_combustible),
                    $linea->tipo_destino === 'vehiculo' ? $linea->vehiculo : null,
                    null,
                    $linea->orden?->fecha_asignacion ?? $linea->created_at
                );

                if (! $estimacion) {
                    $sinPrecio++;
                    continue;
                }

                $linea->update(array_intersect_key($estimacion, array_flip([
                    'combustible_precio', 'ciudad_precio', 'precio_galon',
                    'precio_vigente_desde', 'galones_estimados', 'km_estimados',
                ])));
                $actualizadas++;
            }
        });

        $this->info("Líneas actualizadas: {$actualizadas}. Sin precio disponible: {$sinPrecio}.");

        return self::SUCCESS;
    }
}
