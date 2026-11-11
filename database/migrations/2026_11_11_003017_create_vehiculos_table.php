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
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 15)->unique();
            $table->string('marca', 50);
            $table->string('modelo', 80);
            $table->unsignedSmallInteger('anio')->nullable();
            $table->enum('tipo_combustible', ['diesel', 'gasolina'])->default('diesel');
            $table->boolean('activo')->default(true); // los vehículos se desactivan, no se borran
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
