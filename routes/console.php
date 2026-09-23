<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cbt:auto-submit')->everyMinute()->withoutOverlapping(120);