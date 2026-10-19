<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if (! $request->user()->isDirty()) {
            return redirect()->route('profile.edit')->with('feedback', ['type' => 'info', 'title' => 'Tidak ada perubahan', 'message' => 'Nama dan emailmu masih sama, jadi tidak ada yang disimpan.']);
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Hapus akun secara permanen. Order lama tetap ada sebagai catatan keuangan, tapi dilepas dari akun
     * dan namanya dihapus (lihat User::booted). Admin dan karyawan tidak boleh menghapus akun sendiri,
     * supaya admin terakhir tidak terkunci.
     *
     * Akun dengan kata sandi mengonfirmasi lewat kata sandinya. Akun Google tanpa kata sandi mengonfirmasi
     * lewat login ulang ke Google (GoogleAuthController::confirmDelete), karena kata sandi Google tidak
     * pernah sampai ke aplikasi ini.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canDeleteOwnAccount(), 403);

        if (! $user->hasPassword()) {
            return redirect()->route('profile.edit')
                ->withErrors(['google' => 'Konfirmasi dengan akun Google-mu dulu.'], 'userDeletion');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Masukkan kata sandimu untuk melanjutkan.',
            'password.current_password' => 'Kata sandi salah.',
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->forceDelete();

        return redirect('/')->with('status', 'Akunmu sudah dihapus permanen.');
    }
}
