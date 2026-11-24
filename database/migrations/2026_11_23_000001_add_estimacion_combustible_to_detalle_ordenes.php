<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guarda en cada línea el precio del galón vigente al asignar la orden y lo que alcanzaba
// con el monto. Así la factura y los reportes no cambian cuando el precio cambia de semana.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ordenes', function (Blueprint $table) {
            $table->string('combustible_precio', 20)->nullable()->after('tipo_combustible'); // regular, super, diesel
            $table->string('ciudad_precio', 20)->nullable()->after('combustible_precio');
            $table->decimal('precio_galon', 10, 2)->nullable()->after('ciudad_precio');
            $table->date('precio_vigente_desde')->nullable()->after('precio_galon');
            $table->decimal('galones_estimados', 10, 2)->nullable()->after('precio_vigente_desde');
            $table->decimal('km_estimados', 10, 2)->nullable()->after('galones_estimados');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ordenes', function (Blueprint $table) {
            $table->dropColumn([
                'combustible_precio', 'ciudad_precio', 'precio_galon',
                'precio_vigente_desde', 'galones_estimados', 'km_estimados',
            ]);
        });
    }
};
