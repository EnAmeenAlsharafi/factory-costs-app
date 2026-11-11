<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Operational notifications
|--------------------------------------------------------------------------
|
| Deadlines, stock levels and receivables are scanned through the working day;
| every notification carries a dedupe key, so a pass that finds nothing new tells
| nobody anything. The last pass of the day also prunes what nobody needs.
|
| Shared hosting runs this with one cron entry:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/
Schedule::command('notifications:scan')
    ->everyThirtyMinutes()
    ->between('7:00', '19:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:scan --prune')
    ->dailyAt('22:00')
    ->withoutOverlapping();
