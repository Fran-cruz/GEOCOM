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
        Schema::create('caja_chica', function (Blueprint $table) {
            $table->id();
            // a quién se entrega: pendiente de definir con el cliente, por eso es opcional
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('descripcion');
            $table->date('fecha_entrega');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_chica');
    }
};
