<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';
    protected $fillable = ['placa', 'marca', 'modelo', 'anio', 'tipo_combustible', 'rendimiento_km_litro', 'activo'];
    protected $casts = ['activo' => 'boolean', 'rendimiento_km_litro' => 'decimal:2'];

    public function detalles() { return $this->hasMany(DetalleOrden::class, 'vehiculo_id'); }
}
