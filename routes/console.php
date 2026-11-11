<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revisa cada minuto las órdenes vencidas y avisa por correo.
// withoutOverlapping evita que dos ejecuciones se pisen si una tarda más de un minuto
Schedule::command('ordenes:vencer')->everyMinute()->withoutOverlapping();