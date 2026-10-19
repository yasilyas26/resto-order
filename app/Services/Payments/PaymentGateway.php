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

    /**
     * Minta Snap token untuk satu percobaan bayar. Melempar PaymentGatewayException kalau gagal.
     *
     * @param  list<string>|null  $enabledPayments  batasi popup ke metode ini (nilai enabled_payments Midtrans); null = semua metode
     */
    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes, ?array $enabledPayments = null): string;

    /**
     * Buat transaksi langsung lewat Core API (tanpa popup Snap): hasilnya sudah memuat nomor VA / QR / kode bayar.
     *
     * @param  array<string, mixed>  $spec  payment_type + blok khususnya (lihat PaymentMethods::chargeSpec)
     * @return array<string, mixed> balasan Midtrans (status_code 201 = menunggu pembayaran)
     *
     * @throws PaymentGatewayException kalau Midtrans menolak (mis. metode belum diaktifkan di akun) atau tidak terhubung
     */
    public function charge(string $midtransOrderId, int $grossAmount, string $customerName, array $spec, int $expiryMinutes): array;

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
