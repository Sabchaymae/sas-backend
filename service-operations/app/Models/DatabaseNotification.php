<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseNotification;

class DatabaseNotification extends BaseNotification
{
    protected $connection = 'mysql';
}
