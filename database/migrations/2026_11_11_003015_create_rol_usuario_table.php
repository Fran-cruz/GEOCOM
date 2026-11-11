<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rol_usuario', function (Blueprint $table) {
            $table->id();
            // unique() = cada usuario tiene un solo rol; si se borra el usuario, se borra su fila
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            // restrictOnDelete: no se puede borrar un rol que todavía tiene usuarios
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('activo')->default(true); // activar/desactivar cuenta sin tocar users
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_usuario');
    }
};
