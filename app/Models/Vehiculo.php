<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';
    protected $fillable = ['placa', 'marca', 'modelo', 'anio', 'tipo_combustible', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function detalles() { return $this->hasMany(DetalleOrden::class, 'vehiculo_id'); }
}