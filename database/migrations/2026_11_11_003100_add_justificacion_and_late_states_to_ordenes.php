<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Brings databases created before the create_ordenes edit up to date.
// Idempotent: on a fresh database the create migration already has these changes.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ordenes MODIFY estado ENUM('pendiente','abierta','abierta_tarde','completada','completada_tarde','vencida') NOT NULL DEFAULT 'pendiente'");
        DB::statement('ALTER TABLE ordenes MODIFY fecha_asignacion TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE ordenes MODIFY vence_asignacion TIMESTAMP NULL DEFAULT NULL');

        if (! Schema::hasColumn('ordenes', 'justificacion')) {
            Schema::table('ordenes', function (Blueprint $table) {
                $table->text('justificacion')->nullable()->after('horas_vigencia');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
