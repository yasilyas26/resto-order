<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman dan aksi order hanya untuk pemiliknya: browser yang membuat order itu (kode tercatat di session),
 * atau akun pelanggan yang sama. Tahu kode order saja tidak cukup, jadi URL yang disalin ke browser lain ditolak.
 */
class EnsureOrderOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        if ($order instanceof Order && ! $order->isOwnedBy($request)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pesanan ini hanya bisa dibuka dari perangkat yang membuatnya.'], 403);
            }

            return redirect()->route('home')
                ->with('error', 'Pesanan ini hanya bisa dibuka dari perangkat yang membuatnya.');
        }

        return $next($request);
    }
}
