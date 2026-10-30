<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Table extends Model
{
    use SoftDeletes;

    /** Berapa lama pilihan meja berlaku sejak scan, sebelum pengunjung diminta scan lagi. */
    public const SELECTION_MINUTES = 60;

    // `code` sengaja tidak mass-assignable: hanya boleh dibuat lewat generateCode().
    protected $fillable = ['number', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Meja tidak pernah ada tanpa kode, dari jalur mana pun ia dibuat.
        static::creating(function (Table $table) {
            $table->code ??= static::generateCode($table->number);
        });
    }

    /**
     * Kode acak untuk stiker/QR, mis. "A07-K3F9". Huruf yang mudah tertukar
     * (0/O, 1/I) dibuang supaya mudah diketik manual. Kode yang pernah dipakai
     * (termasuk meja terhapus) tidak pernah dipakai ulang.
     */
    public static function generateCode(string $number): string
    {
        $prefix = substr(Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $number)), 0, 10) ?: 'T';
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = "{$prefix}-{$suffix}";
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /** Meja yang sedang dipilih pengunjung (disimpan di session), selama masih ada dan aktif. */
    public static function fromSession(): ?self
    {
        $id = session('table_id');

        // Batas mutlak sejak scan. Sesi lama tanpa cap waktu dianggap baru mulai sekarang.
        if ($id) {
            $selectedAt = session('table_selected_at');
            if (! $selectedAt) {
                session(['table_selected_at' => now()->timestamp]);
            } elseif (now()->timestamp - $selectedAt > static::SELECTION_MINUTES * 60) {
                session()->forget(['table_id', 'table_selected_at']);
                request()->cookies->remove('table_id');
                $id = null;
            }
        }

        // Sesi bisa ganti/kosong (misalnya saat login); cookie "table_id" jadi cadangan.
        $fromCookie = false;
        if (! $id) {
            $id = request()->cookie('table_id');
            $fromCookie = (bool) $id;
        }

        $table = $id ? static::where('is_active', true)->find($id) : null;

        if ($table && $fromCookie) {
            session(['table_id' => $table->id, 'table_selected_at' => now()->timestamp]);
        }

        return $table;
    }

    /** Cari meja aktif dari kode di stiker/QR. Meja nonaktif atau terhapus dianggap tidak ada. */
    public static function findActiveByCode(string $code): ?self
    {
        return static::where('is_active', true)
            ->where('code', Str::upper(trim($code)))
            ->first();
    }

    /** URL yang dimuat di QR (route /m/{kode} ada di sisi pengunjung). */
    public function scanUrl(): string
    {
        return url('/m/'.$this->code);
    }

    /** Tabel orders baru dibuat di Fase 3, jadi dicek dulu keberadaannya. */
    public function hasOrders(): bool
    {
        return Schema::hasTable('orders')
            && DB::table('orders')->where('table_id', $this->id)->exists();
    }

    public function trashLabel(): string
    {
        return "Meja {$this->number} ({$this->code})";
    }

    public function restoreBlocker(): ?string
    {
        return static::where('number', $this->number)->exists()
            ? "Sudah ada meja aktif dengan nomor {$this->number}."
            : null;
    }

    public function forceDeleteBlocker(): ?string
    {
        return $this->hasOrders()
            ? 'Meja ini sudah pernah punya order, datanya dipertahankan.'
            : null;
    }
}
