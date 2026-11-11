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
        Schema::create('detalle_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->enum('tipo_destino', ['vehiculo', 'mgp']);
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete(); // null si es MGP
            $table->string('descripcion_mgp', 150)->nullable();
            $table->enum('tipo_combustible', ['diesel', 'gasolina']);
            $table->decimal('monto', 10, 2);                // en lempiras
            $table->string('ruta_evidencia')->nullable();   // foto subida por el conductor
            $table->timestamp('fecha_evidencia')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ordenes');
    }
};
