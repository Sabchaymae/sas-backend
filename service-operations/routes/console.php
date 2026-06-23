<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
<<<<<<< HEAD
use Illuminate\Support\Facades\Schedule;
=======
>>>>>>> import/master

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
<<<<<<< HEAD

Schedule::command('tasks:escalate')->everyMinute();
=======
>>>>>>> import/master
