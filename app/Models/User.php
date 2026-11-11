<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // HasApiTokens es el que da createToken() para el login con Sanctum
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // cifra el password automáticamente al guardarlo
        ];
    }

    // Rol del usuario (relación muchos a muchos con 1 solo rol por la restricción unique)
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id')
            ->withPivot('activo')   // permite leer/escribir la columna activo de la tabla intermedia
            ->withTimestamps();
    }

    // Datos de conductor (solo existe si el usuario es conductor)
    public function conductor()
    {
        return $this->hasOne(Conductor::class, 'usuario_id');
    }

    // ¿Tiene este rol y su cuenta está activa? Lo usan los Gates
    public function tieneRol(string $rol): bool
    {
        return $this->roles()
            ->where('nombre', $rol)
            ->wherePivot('activo', true)
            ->exists();
    }
}