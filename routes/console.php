<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('scout:cleanup')->dailyAt('02:00');

// Checks the backup schedule saved in Settings; it only does work when a backup is due.
Schedule::command('scout:backup --scheduled')->everyFiveMinutes()->withoutOverlapping();
