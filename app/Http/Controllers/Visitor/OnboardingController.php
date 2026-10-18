<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    /** Halaman awal: pilih meja lewat scan QR atau ketik kode. Kalau meja sudah dipilih, langsung ke menu. */
    public function index(): View|RedirectResponse
    {
        if (Table::fromSession()) {
            return redirect()->route('menu');
        }

        return view('visitor.onboarding');
    }

    /** Kode yang diketik manual dari stiker meja. */
    public function enter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
        ], [
            'code.required' => 'Masukkan kode meja dulu.',
        ]);

        return $this->select(Table::findActiveByCode($data['code']), withInput: true);
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

        return redirect()->route('home');
    }

    private function select(?Table $table, bool $withInput = false): RedirectResponse
    {
        if (! $table) {
            // Satu pesan untuk semua kemungkinan (salah ketik, meja nonaktif, kode lama), supaya kode tidak bisa ditebak-tebak.
            $back = redirect()->route('home')->withErrors(['code' => 'Kode meja tidak ditemukan atau meja sedang tidak aktif.']);

            return $withInput ? $back->withInput() : $back;
        }

        session(['table_id' => $table->id]);

        return redirect()->route('menu');
    }
}
