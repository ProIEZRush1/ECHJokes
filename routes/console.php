<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cleanup old TTS audio files every hour
\Illuminate\Support\Facades\Schedule::command('vacilada:cleanup-audio --hours=1')->hourly();

// Release stuck calls (redeploys, network issues) every 5 min
\Illuminate\Support\Facades\Schedule::command('vacilada:cleanup-stuck-calls --minutes=10')->everyFiveMinutes();
