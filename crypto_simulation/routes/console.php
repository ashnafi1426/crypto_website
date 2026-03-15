<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule continuous order matching for all active cryptocurrencies
Schedule::command('trading:process-matches --all --async')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Process order matching for all cryptocurrencies');

// Schedule price updates every 30 seconds (using cron job for sub-minute scheduling)
Schedule::command('prices:update')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Update cryptocurrency prices');

// Schedule queue worker monitoring
Schedule::command('queue:monitor trading,prices,default --max=1000')
    ->everyFiveMinutes()
    ->description('Monitor queue health and performance');

// Schedule failed job retry
Schedule::command('queue:retry all')
    ->hourly()
    ->description('Retry failed queue jobs');
// Schedule Ethereum deposit monitoring
Schedule::command('ethereum:check-deposits --blocks=5')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Monitor Ethereum blockchain for deposits');

// Schedule confirmation updates for pending deposits
Schedule::command('ethereum:check-deposits --blocks=1')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Update confirmations for pending deposits');