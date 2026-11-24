<?php

namespace App\Support;

use App\Models\Combustible;
use App\Models\Vehiculo;

// Convierte un monto en lempiras a galones, litros y kilómetros aproximados
// usando el precio oficial de la semana (tabla combustibles).
class EstimadorCombustible
{
    public const LITROS_POR_GALON = 3.785411784;

    public static function ciudad(?string $ciudad = null): string
    {
        return $ciudad ?: config('combustible.ciudad_referencia', 'san_pedro_sula');
    }

    // diesel -> diesel; gasolina -> regular (o super si se pide)
    public static function clavePrecio(string $tipoCombustible, ?string $variante = null): string
    {
        if ($tipoCombustible === 'diesel') {
            return 'diesel';
        }

        return in_array($variante, ['regular', 'super'], true)
            ? $variante
            : config('combustible.gasolina_precio', 'regular');
    }

    /**
     * Devuelve null si no hay precio para ese combustible y ciudad.
     * $fecha = null usa la semana vigente; con fecha usa el precio que regía ese día.
     */
    public static function calcular(
        float $monto,
        string $clavePrecio,
        ?Vehiculo $vehiculo = null,
        ?string $ciudad = null,
        $fecha = null
    ): ?array {
        $ciudad = static::ciudad($ciudad);
        $precio = $fecha
            ? Combustible::precioEn($clavePrecio, $ciudad, $fecha)
            : Combustible::precioVigente($clavePrecio, $ciudad);

        if (! $precio || (float) $precio->precio <= 0) {
            return null;
        }

        $galones = $monto / (float) $precio->precio;
        $litros = $galones * self::LITROS_POR_GALON;
        $rendimiento = $vehiculo ? (float) $vehiculo->rendimiento_km_litro : null;

        return [
            'combustible_precio'   => $clavePrecio,
            'ciudad_precio'        => $ciudad,
            'precio_galon'         => (float) $precio->precio,
            'precio_vigente_desde' => $precio->vigente_desde?->toDateString(),
            'galones_estimados'    => round($galones, 2),
            'litros_estimados'     => round($litros, 2),
            'rendimiento_km_litro' => $rendimiento,
            // MGP no tiene rendimiento: sin kilómetros
            'km_estimados'         => $rendimiento ? round($litros * $rendimiento, 2) : null,
        ];
    }
}
