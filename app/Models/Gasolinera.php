<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasolinera extends Model
{
    protected $table = 'gasolineras';
    protected $fillable = ['nombre', 'direccion', 'codigo_qr', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function ordenes() { return $this->hasMany(Orden::class, 'gasolinera_id'); }
}