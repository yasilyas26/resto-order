<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('updatePassword', [
            // Akun Google yang belum punya kata sandi boleh membuatnya tanpa kata sandi lama.
            'current_password' => $user->hasPassword() ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Kata sandi baru yang sama dengan yang lama tidak mengubah apa pun.
        if ($user->hasPassword() && Hash::check($validated['password'], $user->password)) {
            return back()->with('feedback', ['type' => 'info', 'title' => 'Tidak ada perubahan', 'message' => 'Kata sandi barumu sama dengan yang sekarang. Pakai kata sandi yang berbeda kalau ingin menggantinya.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
