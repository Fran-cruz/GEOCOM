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
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_orden', 30)->unique();
            // unique(): una solicitud genera una sola orden
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->restrictOnDelete();
            $table->foreignId('gasolinera_id')->constrained('gasolineras')->restrictOnDelete();
            $table->foreignId('asignado_por')->constrained('users')->restrictOnDelete(); // admin que asignó
            $table->enum('estado', ['pendiente', 'abierta', 'abierta_tarde', 'completada', 'completada_tarde', 'vencida'])->default('pendiente');
            $table->unsignedTinyInteger('horas_vigencia')->default(1); // 1 a 3 horas
            $table->text('justificacion')->nullable();              // motivo cuando la orden se completa tarde
            $table->timestamp('fecha_asignacion')->nullable();      // momento de la asignación (cuando se genera la orden)
            $table->timestamp('vence_asignacion')->nullable();          // fin de la 1.ª vigencia (antes de escanear)
            $table->timestamp('fecha_apertura')->nullable();// momento del escaneo del QR
            $table->timestamp('vence_apertura')->nullable();// +15 min tras el escaneo
            $table->timestamp('fecha_completada')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'estado']);      // acelera "mis órdenes por estado"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
