<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaChica extends Model
{
    protected $table = 'caja_chica';
    protected $fillable = ['conductor_id', 'registrado_por', 'monto', 'descripcion', 'fecha_entrega'];
    protected $casts = ['monto' => 'decimal:2', 'fecha_entrega' => 'date'];

    public function conductor()  { return $this->belongsTo(Conductor::class, 'conductor_id'); }
    public function registrador(){ return $this->belongsTo(User::class, 'registrado_por'); }
}
