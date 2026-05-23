<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two Factor Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for the two factor authentication
    | system, such as the time-to-live for the generated codes.
    |
    */

    'code_ttl' => env('TWO_FACTOR_CODE_TTL', 15), // in minutes

    'channels' => [
        'email' => 'email',
        'sms' => 'sms',
    ],

];
