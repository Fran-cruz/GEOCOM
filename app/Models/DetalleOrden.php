<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleOrden extends Model
{
    protected $table = 'detalle_ordenes';
    protected $fillable = [
        'orden_id', 'tipo_destino', 'vehiculo_id', 'descripcion_mgp',
        'tipo_combustible', 'monto', 'ruta_evidencia', 'fecha_evidencia',
    ];
    protected $casts = ['monto' => 'decimal:2', 'fecha_evidencia' => 'datetime'];

    public function orden()    { return $this->belongsTo(Orden::class, 'orden_id'); }
    public function vehiculo() { return $this->belongsTo(Vehiculo::class, 'vehiculo_id'); }
}