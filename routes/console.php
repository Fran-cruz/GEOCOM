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

$scraperPython = env(
    'SCRAPER_PYTHON',
    PHP_OS_FAMILY === 'Windows'
        ? base_path('scraper-combustibles/.venv/Scripts/python.exe')
        : base_path('scraper-combustibles/.venv/bin/python')
);

Schedule::exec($scraperPython . ' ' . base_path('scraper-combustibles/scraper.py') . ' run')
    ->dailyAt('05:00')
    ->withoutOverlapping();
