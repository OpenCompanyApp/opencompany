<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agent:resume-waiting')->everyMinute();

Schedule::job(new \App\Jobs\RefreshMcpToolsJob)->hourly();

Schedule::call(function () {
    $dir = storage_path('app/public/charts');
    if (!is_dir($dir)) {
        return;
    }
    $cutoff = now()->subDays(7)->getTimestamp();
    foreach (glob($dir . '/*.png') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
})->daily()->name('cleanup-charts');

Schedule::call(function () {
    $dir = storage_path('app/public/svg');
    if (!is_dir($dir)) {
        return;
    }
    $cutoff = now()->subDays(7)->getTimestamp();
    foreach (glob($dir . '/*.png') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
})->daily()->name('cleanup-svg');
