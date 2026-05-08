<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('currencies:update')->dailyAt('03:00');