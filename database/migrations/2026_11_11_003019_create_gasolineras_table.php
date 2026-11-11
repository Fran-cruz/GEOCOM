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
        Schema::create('gasolineras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('direccion')->nullable();
            $table->string('codigo_qr', 100)->unique(); // texto que contiene el QR físico de la gasolinera
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasolineras');
    }
};
