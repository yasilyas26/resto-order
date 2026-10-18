<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'table' => \App\Http\Middleware\EnsureTableSelected::class,
        ]);
        $middleware->redirectUsersTo(fn ($request) => $request->user()->homeUrl());

        // Di belakang ngrok/hosting, HTTPS diterima proxy lalu diteruskan sebagai HTTP.
        // Tanpa ini url() menghasilkan http:// (QR meja salah, aset diblokir sebagai mixed content).
        $middleware->trustProxies(at: '*');

        // Webhook Midtrans datang dari server Midtrans, tanpa token CSRF.
        // Keasliannya dijamin oleh verifikasi signature_key di controller, bukan oleh CSRF.
        $middleware->validateCsrfTokens(except: ['payments/midtrans/notification']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
