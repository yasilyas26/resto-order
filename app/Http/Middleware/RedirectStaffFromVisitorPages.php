<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Halaman pengunjung (pilih meja, menu, keranjang, riwayat) bukan untuk karyawan/admin.
// Mereka dikembalikan ke area kerjanya. Tamu dan customer lewat seperti biasa.
class RedirectStaffFromVisitorPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isStaff())) {
            return redirect($user->homeUrl());
        }

        return $next($request);
    }
}
