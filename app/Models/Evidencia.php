<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Evidencia extends Model
{
    // Tipos de foto que pide la app por cada línea de combustible
    public const TIPOS = ['bomba', 'tablero', 'tanque', 'factura'];

    protected $table = 'evidencias';
    protected $fillable = ['detalle_orden_id', 'tipo', 'ruta', 'fecha_subida'];
    protected $casts = ['fecha_subida' => 'datetime'];

    // La ruta interna del archivo nunca sale al cliente
    protected $hidden = ['ruta'];

    protected $appends = ['url'];

    // URL de la foto servida por la ruta protegida (/api/evidencias/{id}).
    // Solo se entrega a admin/contabilidad; un conductor (o alguien sin sesión) recibe null.
    protected function url(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->ruta || ! $this->id) {
                return null;
            }

            $usuario = auth('sanctum')->user();
            if (! $usuario || ! $usuario->can('consulta')) {
                return null;
            }

            return route('evidencias.ver', ['id' => $this->id]);
        });
    }

    public function detalle()
    {
        return $this->belongsTo(DetalleOrden::class, 'detalle_orden_id');
    }
}
