<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';
    protected $fillable = [
        'conductor_id', 'tipo_destino', 'vehiculo_id',
        'descripcion_mgp', 'descripcion_actividad', 'estado',
    ];

    public function conductor() { return $this->belongsTo(Conductor::class, 'conductor_id'); }
    public function vehiculo()  { return $this->belongsTo(Vehiculo::class, 'vehiculo_id'); }
    public function orden()     { return $this->hasOne(Orden::class, 'solicitud_id'); }
}
