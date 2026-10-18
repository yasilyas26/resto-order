<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login khusus karyawan dan admin di /portal. Tidak ada link ke sini dari halaman pengunjung (PRD G-13);
 * yang mengamankan area internal tetap middleware, bukan tersembunyinya alamat ini.
 */
class PortalController extends Controller
{
    public function show(Request $request): View
    {
        return view('portal.login', [
            'tab' => $request->query('tab') === 'admin' ? 'admin' : 'staff',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $tab = $request->input('tab') === 'admin' ? Role::Admin : Role::Staff;

        $request->ensureIsNotRateLimited(); // 5 kegagalan per email+IP, lalu dikunci sementara

        $loggedIn = Auth::attempt($request->only('email', 'password'), $request->boolean('remember'));

        // Akun sah tapi bukan untuk tab yang dipilih (pelanggan, atau karyawan di tab Admin) diperlakukan sama dengan
        // password salah: pesannya sama dan ikut dihitung sebagai kegagalan. Dengan begitu orang luar tidak bisa
        // menebak akun mana yang ada, dan tidak bisa mencoba terus-menerus. (Tidak memakai $request->authenticate()
        // karena dia mereset hitungan setiap kali kredensialnya benar, sebelum peran sempat dicek.)
        if (! $loggedIn || Auth::user()->role !== $tab) {
            if ($loggedIn) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($request->throttleKey());
        $user = $request->user();
        $request->session()->regenerate();

        return redirect()->intended($user->homeUrl());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
