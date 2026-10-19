<?php

namespace App\Services\Payments;

/**
 * Pembungkus API Midtrans. Dibuat sebagai interface supaya controller dan tes tidak
 * bergantung langsung ke library/jaringan (tes memakai gateway palsu).
 */
interface PaymentGateway
{
    /** False kalau server key belum diisi di .env. */
    public function isConfigured(): bool;

    /** Minta Snap token untuk satu percobaan bayar. Melempar PaymentGatewayException kalau gagal. */
    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes): string;

    /**
     * Status transaksi menurut Midtrans (dipakai tombol "Cek ulang pembayaran").
     * Null kalau transaksinya belum ada di Midtrans (pelanggan belum memilih metode bayar).
     *
     * @return array<string, mixed>|null
     */
    public function fetchStatus(string $midtransOrderId): ?array;

    /** Batalkan transaksi yang masih menunggu bayar (dipakai saat pelanggan mengganti metode). Melempar PaymentGatewayException kalau ditolak. */
    public function cancel(string $midtransOrderId): void;
}
