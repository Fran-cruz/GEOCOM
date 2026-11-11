<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DetalleOrden extends Model
{
    protected $table = 'detalle_ordenes';
    protected $fillable = [
        'orden_id', 'tipo_destino', 'vehiculo_id', 'descripcion_mgp',
        'tipo_combustible', 'monto', 'ruta_evidencia', 'fecha_evidencia',
    ];
    protected $casts = ['monto' => 'decimal:2', 'fecha_evidencia' => 'datetime'];

    // Se agrega automáticamente a todo JSON que devuelva este modelo
    protected $appends = ['url_evidencia'];

    // URL completa de la foto, o null si aún no hay evidencia.
    // asset() usa el mismo host con el que la app llamó a la API
    // (por ejemplo http://10.0.2.2:8000 desde el emulador), así que la URL le sirve a la app.
    protected function urlEvidencia(): Attribute
    {
        return Attribute::get(
            fn () => $this->ruta_evidencia ? asset('storage/' . $this->ruta_evidencia) : null
        );
    }

    public function orden()    { return $this->belongsTo(Orden::class, 'orden_id'); }
    public function vehiculo() { return $this->belongsTo(Vehiculo::class, 'vehiculo_id'); }
    //si
}