<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIpIsNotRateLimited();
        $this->ensureIsNotRateLimited();

        $loggedIn = Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));

        // /login hanya untuk pelanggan. Admin dan karyawan masuk lewat /portal; di sini mereka diperlakukan sama
        // dengan password salah (pesan sama, ikut dihitung sebagai kegagalan) supaya akun internal tidak bisa
        // ditebak atau dicoba-coba dari halaman publik.
        if ($loggedIn && Auth::user()->role !== Role::Customer) {
            Auth::guard('web')->logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();
            $loggedIn = false;
        }

        if (! $loggedIn) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), self::IP_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Maksimal 5 login GAGAL per IP dalam 5 menit, berapa pun emailnya (menahan tebak-tebak ganti-ganti email). */
    private const IP_MAX_FAILURES = 5;
    private const IP_DECAY_SECONDS = 300;

    /**
     * Hanya kegagalan yang dihitung: membuka halaman login atau login berhasil tidak memakai jatah.
     * Sengaja tidak di-clear saat sukses, supaya penyerang tidak bisa mereset hitungan dengan login ke akunnya sendiri.
     *
     * @throws ThrottleRequestsException
     */
    public function ensureIpIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::IP_MAX_FAILURES)) {
            return;
        }

        throw new ThrottleRequestsException(
            'Too Many Requests',
            null,
            ['Retry-After' => RateLimiter::availableIn($this->ipThrottleKey())],
        );
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
