<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';
    // foto_ruta no está en $fillable: solo la asigna el controlador de foto
    protected $fillable = ['placa', 'marca', 'modelo', 'anio', 'tipo_combustible', 'rendimiento_km_litro', 'activo'];
    protected $casts = ['activo' => 'boolean', 'rendimiento_km_litro' => 'decimal:2'];

    // La ruta interna del archivo nunca sale al cliente
    protected $hidden = ['foto_ruta'];

    protected $appends = ['foto_url'];

    // URL de la foto servida por la ruta protegida (/api/vehiculos/{id}/foto).
    // Es null si el vehículo no tiene foto. El parámetro v cambia al subir una
    // foto nueva, así el cliente no muestra la anterior guardada en caché.
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->foto_ruta || ! $this->id) {
                return null;
            }

            return route('vehiculos.foto', [
                'id' => $this->id,
                'v'  => $this->updated_at?->timestamp,
            ]);
        });
    }

    public function detalles() { return $this->hasMany(DetalleOrden::class, 'vehiculo_id'); }
}
