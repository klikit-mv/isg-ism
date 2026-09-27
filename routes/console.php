<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('scout:cleanup')->dailyAt('02:00');
