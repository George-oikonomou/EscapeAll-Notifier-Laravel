<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // All commands are auto-discovered via load() below — no need to list them here.

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('ea:sync-companies-areas')->dailyAt('01:50');
        $schedule->command('rooms:sync-fast')->dailyAt('02:00');
        $schedule->command('availability:sync-fast')->dailyAt('03:00');
        $schedule->command('reminders:notify-availability')->everyTenMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
