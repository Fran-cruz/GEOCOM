<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_orden_id')->constrained('detalle_ordenes')->cascadeOnDelete();
            // Las 4 fotos que pide la app por cada línea de combustible
            $table->enum('tipo', ['bomba', 'tablero', 'tanque', 'factura']);
            $table->string('ruta');
            $table->timestamp('fecha_subida')->nullable();
            $table->timestamps();

            // Una sola foto de cada tipo por línea
            $table->unique(['detalle_orden_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias');
    }
};
