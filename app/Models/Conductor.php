<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conductor extends Model
{
    protected $table = 'conductores';
    protected $fillable = ['usuario_id', 'telefono', 'cargo', 'activo'];
    protected $casts = ['activo' => 'boolean'];       // devuelve true/false en vez de 1/0

    public function usuario()    { return $this->belongsTo(User::class, 'usuario_id'); }
    public function solicitudes(){ return $this->hasMany(Solicitud::class, 'conductor_id'); }
    public function ordenes()    { return $this->hasMany(Orden::class, 'conductor_id'); }
}
