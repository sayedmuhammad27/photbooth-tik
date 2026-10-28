<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Admin yang sudah login tidak perlu melihat halaman login lagi.
        $middleware->redirectUsersTo('/admin/dashboard');

        // Di belakang tunnel (ngrok/cloudflared) skema HTTPS datang lewat header X-Forwarded-*.
        $middleware->trustProxies(at: '*');

        // Kecualikan endpoint streaming kamera dari validasi CSRF agar tidak membebani CPU
        $middleware->validateCsrfTokens(except: [
            'camera-hub/push',
            'camera-hub/snapshot/push',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sesi/CSRF kedaluwarsa (HTTP 419): tampilkan pesan ramah, bukan halaman error mentah.
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
            }

            return redirect()->back()
                ->withInput($request->except(['password', '_token']))
                ->withErrors(['email' => 'Sesi halaman kedaluwarsa. Silakan coba lagi.']);
        });
    })->create();
