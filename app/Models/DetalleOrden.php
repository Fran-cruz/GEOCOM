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

    // La ruta interna del archivo nunca sale al cliente
    protected $hidden = ['ruta_evidencia'];

    // Se agrega automáticamente a todo JSON que devuelva este modelo
    protected $appends = ['url_evidencia'];

    // URL de la foto servida por la ruta protegida (/api/detalle-ordenes/{id}/evidencia).
    // Solo se entrega a admin/contabilidad; un conductor (o alguien sin sesión) recibe null.
    protected function urlEvidencia(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->ruta_evidencia || ! $this->id) {
                return null;
            }

            $usuario = auth('sanctum')->user();
            if (! $usuario || ! $usuario->can('consulta')) {
                return null;
            }

            return route('detalle-ordenes.evidencia', ['id' => $this->id]);
        });
    }

    public function orden()    { return $this->belongsTo(Orden::class, 'orden_id'); }
    public function vehiculo() { return $this->belongsTo(Vehiculo::class, 'vehiculo_id'); }
}
