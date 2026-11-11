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
        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            // nombre y correo viven en users; aquí solo van los datos propios del conductor
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('telefono', 20);
            $table->string('cargo', 100);             // técnico, técnico jefe de cuadrilla
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conductores');
    }
};
