<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    /**
     * Cek kode 6 digit yang diketik user yang sedang login.
     * Batas percobaan ditangani di User::verifyEmailCode (5 kali salah = kode hangus).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Masukkan kode 6 digit dari email.',
            'code.digits' => 'Kode harus 6 angka.',
        ]);

        if (! $user->verifyEmailCode($data['code'])) {
            throw ValidationException::withMessages(['code' => $this->failureMessage($user)]);
        }

        event(new Verified($user));

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    private function failureMessage(User $user): string
    {
        // Kode sudah dihapus = kedaluwarsa, kebanyakan salah, atau memang belum pernah dikirim.
        if ($user->email_verification_code === null) {
            return 'Kode sudah tidak berlaku. Klik "Kirim Ulang Kode" untuk minta kode baru.';
        }

        $left = User::EMAIL_CODE_MAX_ATTEMPTS - $user->email_verification_attempts;

        return "Kode salah. Sisa {$left} percobaan.";
    }
}
