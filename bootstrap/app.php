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
            'visitor' => \App\Http\Middleware\RedirectStaffFromVisitorPages::class,
            'table' =>\App\Http\Middleware\EnsureTableSelected::class,
            'order.owner' => \App\Http\Middleware\EnsureOrderOwner::class,
        ]);
        $middleware->redirectUsersTo(fn ($request) => $request->user()->homeUrl());

        // Di belakang ngrok/hosting, HTTPS diterima proxy lalu diteruskan sebagai HTTP.
        // Tanpa ini url() menghasilkan http:// (QR meja salah, aset diblokir sebagai mixed content).
        // Hanya proxy yang terdaftar di TRUSTED_PROXIES yang dipercaya (daftar IP dipisah koma, atau * untuk
        // semuanya, hanya untuk dev). Kosong = tidak ada yang dipercaya, supaya header X-Forwarded-For palsu
        // tidak bisa dipakai menghindari pembatas login.
        $trusted = trim((string) env('TRUSTED_PROXIES'));

        if ($trusted !== '') {
            $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        }

        // Webhook Midtrans datang dari server Midtrans, tanpa token CSRF.
        // Keasliannya dijamin oleh verifikasi signature_key di controller, bukan oleh CSRF.
        $middleware->validateCsrfTokens(except: ['payments/midtrans/notification']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
