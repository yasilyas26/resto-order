<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sampah dikosongkan otomatis setelah 30 hari. Butuh `php artisan schedule:run` tiap menit (cron / Task Scheduler) di server.
Schedule::command('trash:purge')->dailyAt('03:00');
