<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // firstOrCreate permite correr el seeder varias veces sin duplicar roles
        foreach ([['admin', 'Administrador'], ['contabilidad', 'Contabilidad'], ['conductor', 'Conductor']] as [$nombre, $etiqueta]) {
            \App\Models\Rol::firstOrCreate(['nombre' => $nombre], ['etiqueta' => $etiqueta]);
        }
    }
}
