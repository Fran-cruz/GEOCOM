<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_imagenes', function (Blueprint $table) {
            $table->char('sha256', 64)->primary();
            $table->string('url', 500)->nullable();
            $table->string('fuente', 20);
            $table->date('vigente_desde')->nullable();
            $table->integer('puntaje')->nullable();
            $table->string('estado', 20);
            $table->text('motivo')->nullable();
            $table->dateTime('procesada_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_imagenes');
    }
};
