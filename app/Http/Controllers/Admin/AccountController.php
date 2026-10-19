<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Menu akun di pojok kanan atas admin: ganti nama dan ganti kata sandi. Email tidak diubah dari sini. */
class AccountController extends Controller
{
    public function updateName(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('adminName', [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update(['name' => trim($data['name'])]);

        return back()->with('status', 'Nama berhasil diganti.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('adminPassword', [
            // Akun yang belum punya kata sandi (mis. masuk lewat Google) boleh membuatnya tanpa kata sandi lama.
            'current_password' => $user->hasPassword() ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Kata sandi berhasil diganti.');
    }
}
