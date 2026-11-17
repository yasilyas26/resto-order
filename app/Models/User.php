<?php

namespace App\Models;

use App\Notifications\VerifyEmailCode;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use \App\Enums\Role;
use \Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    /** Kode verifikasi email berlaku 10 menit dan hangus setelah 5 kali salah. */
    public const EMAIL_CODE_TTL_MINUTES = 10;
    public const EMAIL_CODE_MAX_ATTEMPTS = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    protected static function booted(): void
    {
        // Hapus permanen: order tidak pernah dihapus (catatan keuangan), jadi dilepas dari akun dan
        // data pribadinya dihapus. Berlaku untuk hapus oleh pemilik akun maupun oleh admin dari Sampah.
        static::forceDeleting(function (User $user) {
            Order::where('user_id', $user->id)->update([
                'user_id' => null,
                'customer_name' => 'Pelanggan terhapus',
                'customer_contact' => null,
            ]);

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
    }

    /** Dipanggil Laravel saat user baru daftar: kirim kode, bukan link. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailCode($this->issueEmailVerificationCode()));
    }

    /**
     * Buat kode 6 digit baru (kode lama otomatis tidak berlaku). Yang disimpan hanya hash-nya;
     * kode asli dikembalikan sekali ini saja supaya bisa dikirim lewat email.
     */
    public function issueEmailVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(self::EMAIL_CODE_TTL_MINUTES),
            'email_verification_attempts' => 0,
        ])->save();

        return $code;
    }

    /** Cek kode yang diketik user. Benar: email ditandai terverifikasi. Salah: hitungan percobaan naik. */
    public function verifyEmailCode(string $input): bool
    {
        if ($this->email_verification_code === null
            || $this->email_verification_expires_at === null
            || $this->email_verification_expires_at->isPast()
            || $this->email_verification_attempts >= self::EMAIL_CODE_MAX_ATTEMPTS) {
            $this->clearEmailVerificationCode();

            return false;
        }

        if (! Hash::check($input, $this->email_verification_code)) {
            $this->increment('email_verification_attempts');

            if ($this->email_verification_attempts >= self::EMAIL_CODE_MAX_ATTEMPTS) {
                $this->clearEmailVerificationCode();
            }

            return false;
        }

        $this->markEmailAsVerified();
        $this->clearEmailVerificationCode();

        return true;
    }

    public function clearEmailVerificationCode(): void
    {
        $this->forceFill([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    /** Hanya pelanggan yang boleh menghapus akunnya sendiri; admin dan karyawan dikelola admin (mencegah admin terakhir terkunci). */
    public function canDeleteOwnAccount(): bool
    {
        return $this->role === Role::Customer;
    }

    /** Akun yang daftar lewat Google belum tentu punya kata sandi. */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function trashLabel(): string
    {
        return "{$this->name} ({$this->email})";
    }

    public function restoreBlocker(): ?string
    {
        return null;
    }

    public function forceDeleteBlocker(): ?string
    {
        return null;
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::Staff;
    }

    public function homeUrl(): string
    {
        return match ($this->role)
        {
            Role::Admin => route('admin.index', absolute: false),
            Role::Staff => route('staff.orders.index', absolute: false),
            default => route('dashboard', absolute: false),
        };
    }
}
