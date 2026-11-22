<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_ejecuciones', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('ejecutado_en')->useCurrent();
            $table->string('resultado', 30);
            $table->string('fuente', 20)->nullable();
            $table->date('vigente_desde')->nullable();
            $table->text('detalle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_ejecuciones');
    }
};
