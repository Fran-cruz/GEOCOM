<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
