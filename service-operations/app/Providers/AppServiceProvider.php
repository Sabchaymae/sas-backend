<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
<<<<<<< HEAD
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
=======
>>>>>>> import/master

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
<<<<<<< HEAD
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
=======
        //
>>>>>>> import/master
    }
}
