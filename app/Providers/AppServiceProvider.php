<?php

namespace App\Providers;

use App\Models\Table;
use App\Services\Payments\MidtransGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Controller meminta PaymentGateway; tes bisa menukarnya dengan gateway palsu.
        $this->app->bind(PaymentGateway::class, MidtransGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            // Cek password bocor memanggil layanan internet; dilewati saat menjalankan tes.
            ->when(! app()->runningUnitTests(), fn (Password $rule) => $rule->uncompromised()));

        // Tebak-tebak kode meja: dibatasi per IP.
        RateLimiter::for('table-code', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Checkout: dibatasi per sesi pengunjung, plus batas lebih longgar per IP (beberapa orang bisa berbagi Wi-Fi).
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinutes(10, 10)->by('sesi:'.$request->session()->getId()),
            Limit::perMinutes(10, 40)->by('ip:'.$request->ip()),
        ]);

        // Header halaman pengunjung menampilkan meja yang sedang dipilih.
        View::composer('components.visitor-layout', function ($view) {
            $view->with('currentTable', Table::fromSession());
        });
    }
}
