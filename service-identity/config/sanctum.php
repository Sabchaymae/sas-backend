<?php


use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |

    | Requests from the following domains / hosts will receive stateful CSRF
    | protection by Sanctum. These domains typically include your local
    | and production domains which access your API via a frontend.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1%s',
        env('APP_URL') ? ',' . parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),


    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------

    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired.
    |
    */


    'expiration' => 60 * 24, // 24 hours in minutes


    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------

    |
    | Sanctum can prefix new tokens in order to take advantage of various
    | automated security scanning tools. 
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'oid_'),


    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your multi-page single page application, Sanctum
    | will need to inject a CSRF token cookie into the response.
    |
    */

    'middleware' => [
        'stateful' => [
            EnsureFrontendRequestsAreStateful::class,
        ],
    ],


];
