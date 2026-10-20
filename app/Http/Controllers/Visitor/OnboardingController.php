<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    /** Halaman awal: pilih meja lewat scan QR. Kalau meja sudah dipilih, langsung ke menu. */
    public function index(): View|RedirectResponse
    {
        if (Table::fromSession()) {
            return redirect()->route('menu');
        }

        return view('visitor.onboarding');
    }

    /** Hasil scan QR: /m/{kode}. */
    public function scan(string $code): RedirectResponse
    {
        return $this->select(Table::findActiveByCode($code));
    }

    /** Tombol "Ganti meja": lupakan meja yang dipilih. Isi keranjang tetap tersimpan di perangkat. */
    public function change(Request $request): RedirectResponse
    {
        $request->session()->forget('table_id');

        return redirect()->route('home')->withCookie(cookie()->forget('table_id'));
    }

    private function select(?Table $table): RedirectResponse
    {
        if (! $table) {
            // Satu pesan untuk semua kemungkinan (QR salah, meja nonaktif, kode lama), supaya kode tidak bisa ditebak-tebak.
            return redirect()->route('home')->withErrors(['code' => 'QR meja tidak dikenali atau meja sedang tidak aktif. Coba scan ulang.']);
        }

        session(['table_id' => $table->id]);

        // Cadangan 6 jam kalau sesi hilang (mis. saat login). Dicek aktif tiap request lewat Table::fromSession().
        return redirect()->route('menu')->withCookie(cookie('table_id', (string) $table->id, 360));
    }
}
