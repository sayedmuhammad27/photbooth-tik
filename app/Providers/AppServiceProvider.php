<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Paksa HTTPS hanya bila APP_URL memakai HTTPS. Lewat Cloudflare Tunnel skema HTTPS sudah
        // terdeteksi dari header X-Forwarded-Proto (trustProxies), sehingga akses http://localhost
        // dan Wi-Fi/LAN tetap berjalan walau APP_ENV=production.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Pembatas laju bernama. (throttle:N,1 tanpa nama memakai satu penghitung bersama per IP,
        // sehingga live view yang sering dipanggil akan ikut menghabiskan jatah rute lain.)
        RateLimiter::for('booth-process', fn (Request $r) => Limit::perMinute(30)->by('process|' . $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(8)->by('login|' . $r->ip()));
        RateLimiter::for('digicam-live', fn (Request $r) => Limit::perMinute(3000)->by('dlive|' . $r->ip()));
        RateLimiter::for('digicam-capture', fn (Request $r) => Limit::perMinute(20)->by('dcap|' . $r->ip()));
    }
}