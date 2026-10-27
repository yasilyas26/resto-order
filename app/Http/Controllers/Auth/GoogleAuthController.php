<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        // Selalu tanya akun mana: sesi Google di browser tidak ikut hilang saat logout dari sini.
        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    /**
     * Konfirmasi hapus akun untuk akun Google tanpa kata sandi: login ulang ke Google dan pilih akun.
     * Aplikasi ini tidak pernah melihat kata sandi Google, jadi yang diverifikasi adalah akun Google yang dipilih.
     */
    public function confirmDelete(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canDeleteOwnAccount() && ! $user->hasPassword() && $user->google_id, 403);

        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret'))) {
            return redirect()->route('profile.edit')->withErrors(['google' => 'Konfirmasi dengan Google belum disiapkan.'], 'userDeletion');
        }

        session(['google_confirm_delete' => $user->id]);

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        // Sudah login: ini kembalian dari konfirmasi hapus akun, bukan login.
        if (Auth::check()) {
            return $this->finishDeleteConfirmation($request);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Pengunjung hanya melihat pesan umum; penyebab sebenarnya (state, secret, jaringan) dicatat di log.
            report($e);

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

        // Akun hasil register yang emailnya belum terverifikasi bisa saja dibuat orang lain dengan email ini
        // beserta password pilihannya. Google membuktikan pemilik email yang sebenarnya, jadi password lama
        // dibuang dan semua sesi/remember-me lamanya ditutup sebelum akun ditautkan.
        if ($user->exists && $user->email_verified_at === null) {
            $user->password = null;
            $user->setRememberToken(Str::random(60));
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        // Email yang sama dengan akun hasil register: akun ditautkan (google_id diisi), bukan dibuat ganda.
        $user->google_id ??= $google->getId();
        // Google sudah memverifikasi emailnya.
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended($user->homeUrl());
    }

    private function finishDeleteConfirmation(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Hanya sah kalau memang dimulai dari tombol konfirmasi di halaman profil, oleh akun yang sama.
        if ((int) $request->session()->pull('google_confirm_delete') !== $user->id) {
            return redirect()->to($user->homeUrl());
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('profile.edit')->withErrors(['google' => 'Konfirmasi dengan Google dibatalkan atau gagal. Coba lagi.'], 'userDeletion');
        }

        if ($google->getId() !== $user->google_id) {
            return redirect()->route('profile.edit')->withErrors(['google' => 'Akun Google yang kamu pilih bukan akun yang tertaut. Akunmu tidak dihapus.'], 'userDeletion');
        }

        abort_unless($user->canDeleteOwnAccount(), 403);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $user->forceDelete();

        return redirect('/')->with('status', 'Akunmu sudah dihapus permanen.');
    }
}
