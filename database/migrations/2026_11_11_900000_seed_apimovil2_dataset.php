<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

// Loads the test dataset once. Skips when the database already has users.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            return;
        }

        DB::unprepared(File::get(database_path('seeds/apimovil2_dataset.sql')));
    }

    public function down(): void
    {
        //
    }
};
