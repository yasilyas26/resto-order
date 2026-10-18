<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Login Google khusus pelanggan. Akun admin dan karyawan dibuat Admin dan tidak boleh masuk lewat Google. */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret'))) {
            return redirect()->route('login')->withErrors(['email' => 'Login dengan Google belum disiapkan.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Login dengan Google dibatalkan atau gagal. Coba lagi.']);
        }

        // withTrashed: email akun yang sudah dihapus tetap terpakai (unique), jadi harus ketahuan di sini.
        $user = User::withTrashed()->where('google_id', $google->getId())->first()
            ?? User::withTrashed()->where('email', $google->getEmail())->first();

        if ($user?->trashed()) {
            return redirect()->route('login')->withErrors(['email' => 'Akun ini sudah dinonaktifkan.']);
        }

        if ($user && $user->role !== Role::Customer) {
            // Mencegah pengambilalihan akun internal hanya karena emailnya sama dengan akun Google seseorang.
            return redirect()->route('login')->withErrors(['email' => 'Akun ini tidak bisa masuk lewat Google. Gunakan email dan kata sandi.']);
        }

        if (! $user) {
            $user = new User(['name' => $google->getName() ?: $google->getEmail(), 'email' => $google->getEmail()]);
            // role tidak mass-assignable; defaultnya "customer".
        }

        // Email yang sama dengan akun hasil register: akun ditautkan (google_id diisi), bukan dibuat ganda.
        $user->google_id ??= $google->getId();
        // Google sudah memverifikasi emailnya.
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended($user->homeUrl());
    }
}
