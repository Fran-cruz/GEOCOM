<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combustibles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('combustible', 20);
            $table->string('ciudad', 20);
            $table->decimal('precio', 10, 2);
            $table->decimal('variacion', 10, 2);
            $table->decimal('apoyo', 10, 2)->nullable();
            $table->date('vigente_desde');
            $table->string('estado', 20);
            $table->string('fuente', 20);
            $table->string('imagen_url', 500)->nullable();
            $table->char('imagen_sha256', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['combustible', 'ciudad', 'vigente_desde'], 'uq_combustible_ciudad_semana');
            $table->index('vigente_desde', 'ix_combustibles_vigente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combustibles');
    }
};
