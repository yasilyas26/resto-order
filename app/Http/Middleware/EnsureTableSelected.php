<?php

namespace App\Http\Middleware;

use App\Models\Table;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman pesan (menu, keranjang) hanya bisa dibuka kalau pengunjung sudah memilih meja yang masih aktif.
 * Dicek tiap request, jadi meja yang dinonaktifkan admin langsung tidak bisa dipakai lagi.
 */
class EnsureTableSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $table = Table::fromSession();

        if (! $table) {
            $request->session()->forget('table_id');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Meja belum dipilih atau sudah tidak aktif. Pilih meja lagi.',
                    'redirect' => route('home'),
                ], 409);
            }

            return redirect()->route('home')->with('error', 'Pilih meja dulu untuk mulai memesan.');
        }

        $request->attributes->set('table', $table);

        return $next($request);
    }
}
