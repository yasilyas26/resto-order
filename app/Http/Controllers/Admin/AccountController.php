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

        $name = trim($data['name']);

        if ($name === $request->user()->name) {
            return back()->with('feedback', ['type' => 'info', 'title' => 'Tidak ada perubahan', 'message' => 'Namamu masih sama dengan yang sekarang, jadi tidak ada yang disimpan.']);
        }

        $request->user()->update(['name' => $name]);

        return back()->with('name_changed', true);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('adminPassword', [
            // Akun yang belum punya kata sandi (mis. masuk lewat Google) boleh membuatnya tanpa kata sandi lama.
            'current_password' => $user->hasPassword() ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Kata sandi baru yang sama dengan yang lama tidak mengubah apa pun.
        if ($user->hasPassword() && Hash::check($data['password'], $user->password)) {
            return back()->with('feedback', ['type' => 'info', 'title' => 'Tidak ada perubahan', 'message' => 'Kata sandi barumu sama dengan yang sekarang. Pakai kata sandi yang berbeda kalau ingin menggantinya.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Ditampilkan sebagai modal di layout admin (bukan banner biasa), supaya jelas berhasilnya.
        return back()->with('password_changed', true);
    }
}
