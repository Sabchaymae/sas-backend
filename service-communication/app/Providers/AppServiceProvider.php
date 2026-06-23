<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Broadcast;

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
        // Use custom personal access token model configured for identity service database connection
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Register custom stateless broadcasting routes with API and auth:sanctum middleware
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

        // Require the channel authorization definitions manually
        require base_path('routes/channels.php');
    }
}
