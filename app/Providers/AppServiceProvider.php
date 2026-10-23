<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        // Paksa link CSS/JS menggunakan HTTPS jika URL menggunakan HTTPS (seperti Ngrok)
        if (str_contains(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    }
}