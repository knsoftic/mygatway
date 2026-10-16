<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (run `php artisan schedule:work` locally, cron in production)
|--------------------------------------------------------------------------
*/
Schedule::command('webhooks:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('gateway:sync-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('gateway:check-providers')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('gateway:reconcile --ledger')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('gateway:reconcile --provider=cryptomus --days=2')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('gateway:reconcile --provider=binance_pay --days=2')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('gateway:sync-currencies')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('gateway:prune-logs')->dailyAt('04:00');
Schedule::command('queue:prune-failed --hours=720')->daily();
