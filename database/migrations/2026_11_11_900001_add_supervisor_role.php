<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Rol::firstOrCreate(
            ['nombre' => 'supervisor'],
            ['etiqueta' => 'Supervisor']
        );
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('nombre', 'supervisor')->value('id');

        if ($roleId) {
            DB::table('rol_usuario')->where('rol_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
