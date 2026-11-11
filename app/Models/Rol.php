<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';                       // nombre explícito: Laravel buscaría "rols"
    protected $fillable = ['nombre', 'etiqueta'];

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'rol_usuario', 'rol_id', 'usuario_id');
    }
}
