<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Payment;

/**
 * Cara membayar yang ditampilkan setelah pelanggan memilih metode: QR untuk QRIS/GoPay, nomor Virtual Account,
 * kode pembayaran minimarket, dan sebagainya. Datanya diambil dari balasan Midtrans yang tersimpan di
 * payments.raw_payload (webhook atau "cek ulang"), jadi hanya ada setelah Midtrans mencatat transaksinya.
 */
class PaymentInstructions
{
    /**
     * Null kalau belum ada petunjuk: belum memilih metode di popup, sudah lunas, atau metodenya kartu.
     *
     * @return array<string, mixed>|null
     */
    public static function for(Order $order): ?array
    {
        if ($order->isPaid()) {
            return null;
        }

        /** @var Payment|null $payment */
        $payment = $order->payments->sortByDesc('id')->first(fn (Payment $p) => $p->transaction_status === 'pending' && is_array($p->raw_payload));

        if (! $payment) {
            return null;
        }

        $instructions = self::build($payment->raw_payload, $order->total);

        // API status Midtrans tidak memuat kode QR; gambarnya diambil lewat route kita (lihat PaymentQrController).
        if ($instructions && $instructions['kind'] === 'qr' && $instructions['qr'] === null) {
            $instructions['qr'] = ['svg' => null, 'image' => route('order.qr', $order)];
        }

        return $instructions;
    }

    /**
     * @param  array<string, mixed>  $p  balasan Midtrans
     * @return array{kind: string, title: string, amount: int, qr: ?array{svg: ?string, image: ?string}, deeplink: ?string, fields: list<array{label: string, value: string}>, steps: list<string>}|null
     */
    public static function build(array $p, int $amount): ?array
    {
        $type = $p['payment_type'] ?? null;
        $actions = collect($p['actions'] ?? [])->mapWithKeys(fn ($a) => [($a['name'] ?? '') => $a['url'] ?? null]);
        $deeplink = $actions['deeplink-redirect'] ?? null;
        $qrString = $p['qr_string'] ?? null;
        $qrImage = $actions['generate-qr-code'] ?? null;

        $qr = $qrString || $qrImage ? [
            // Teks QR digambar sendiri (SVG tajam, tanpa memuat gambar dari luar); gambar Midtrans hanya cadangan.
            'svg' => $qrString ? preg_replace('/^<\?xml[^>]*\?>\s*/', '', QrSvg::make((string) $qrString, 260)) : null,
            'image' => $qrString ? null : $qrImage,
        ] : null;

        $base = ['amount' => $amount, 'qr' => null, 'deeplink' => null, 'fields' => [], 'steps' => []];

        return match ($type) {
            // Tanpa qr_string/actions (balasan status Midtrans), tetap ada petunjuk QR: gambarnya ditambahkan di for().
            'qris', 'gopay' => ($qr || $deeplink || isset($p['transaction_id'])) ? array_merge($base, [
                'kind' => 'qr',
                'title' => $type === 'gopay' ? 'Bayar dengan GoPay' : 'Scan QRIS untuk membayar',
                'qr' => $qr,
                'deeplink' => $type === 'gopay' ? $deeplink : null,
                'steps' => $type === 'gopay'
                    ? ['Buka aplikasi Gojek di HP-mu, atau scan kode QR ini dari HP lain.', 'Pastikan nominalnya sesuai, lalu konfirmasi pembayaran.', 'Halaman ini berubah otomatis setelah pembayaran diterima.']
                    : ['Buka e-wallet atau mobile banking apa pun yang mendukung QRIS.', 'Scan kode QR di bawah, pastikan nominalnya sesuai, lalu konfirmasi.', 'Halaman ini berubah otomatis setelah pembayaran diterima.'],
            ]) : null,
            'shopeepay' => $deeplink ? array_merge($base, [
                'kind' => 'app',
                'title' => 'Bayar dengan ShopeePay',
                'deeplink' => $deeplink,
                'steps' => ['Tekan tombol di bawah untuk membuka aplikasi Shopee.', 'Konfirmasi pembayaran di aplikasi.', 'Kembali ke halaman ini, statusnya berubah otomatis.'],
            ]) : null,
            'bank_transfer' => self::virtualAccount($p, $base),
            'echannel' => isset($p['bill_key']) ? array_merge($base, [
                'kind' => 'va',
                'title' => 'Transfer Mandiri Bill Payment',
                'fields' => [
                    ['label' => 'Kode Perusahaan', 'value' => (string) ($p['biller_code'] ?? '70012')],
                    ['label' => 'Kode Pembayaran', 'value' => (string) $p['bill_key']],
                ],
                'steps' => ['Buka Livin\' by Mandiri atau ATM Mandiri, pilih Bayar lalu Multipayment.', 'Masukkan kode perusahaan dan kode pembayaran di bawah.', 'Konfirmasi nominal dan selesaikan pembayaran.'],
            ]) : null,
            'cstore' => isset($p['payment_code']) ? array_merge($base, [
                'kind' => 'store',
                'title' => 'Bayar di '.ucfirst((string) ($p['store'] ?? 'minimarket')),
                'fields' => [['label' => 'Kode Pembayaran', 'value' => (string) $p['payment_code']]],
                'steps' => ['Datang ke kasir '.ucfirst((string) ($p['store'] ?? 'minimarket')).' terdekat.', 'Sebutkan kode pembayaran di bawah dan bayar tunai sesuai nominal.', 'Simpan struknya sampai statusnya berubah di halaman ini.'],
            ]) : null,
            default => null,
        };
    }

    /** @param  array<string, mixed>  $base */
    private static function virtualAccount(array $p, array $base): ?array
    {
        $va = $p['va_numbers'][0] ?? null;

        if ($va) {
            $bank = strtoupper((string) ($va['bank'] ?? ''));
            $number = (string) ($va['va_number'] ?? '');
        } elseif (isset($p['permata_va_number'])) {
            $bank = 'PERMATA';
            $number = (string) $p['permata_va_number'];
        } else {
            return null;
        }

        return array_merge($base, [
            'kind' => 'va',
            'title' => "Transfer ke Virtual Account {$bank}",
            'fields' => [['label' => "Nomor Virtual Account {$bank}", 'value' => $number]],
            'steps' => ["Buka aplikasi mobile banking atau ATM {$bank}, pilih Transfer lalu Virtual Account.", 'Masukkan nomor Virtual Account di bawah dan pastikan nominalnya sesuai.', 'Halaman ini berubah otomatis setelah pembayaran diterima.'],
        ]);
    }
}
