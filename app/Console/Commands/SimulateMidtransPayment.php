<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\MidtransSignature;
use App\Services\Payments\NotificationResult;
use App\Services\Payments\PaymentNotificationHandler;
use Illuminate\Console\Command;

/**
 * Alat bantu pengembangan: meniru notifikasi pembayaran dari Midtrans untuk sebuah order, tanpa membuka popup
 * atau simulator. Notifikasinya ditandatangani dengan server key sungguhan lalu diproses oleh handler yang
 * SAMA dengan webhook, jadi pemeriksaan signature dan nominal tetap berjalan. Hanya jalan di lingkungan lokal.
 */
class SimulateMidtransPayment extends Command
{
    protected $signature = 'midtrans:simulate
        {code : Kode order, mis. ORD-825S5J (atau ID Midtrans lengkap, mis. ORD-825S5J-2)}
        {--status=settlement : settlement, pending, expire, cancel, atau deny}
        {--type=qris : payment_type yang dicatat}';

    protected $description = 'Meniru notifikasi pembayaran Midtrans untuk sebuah order (khusus lokal)';

    private const STATUSES = ['settlement', 'pending', 'expire', 'cancel', 'deny'];

    public function handle(PaymentNotificationHandler $handler): int
    {
        if (! app()->environment(['local', 'testing']) || config('midtrans.is_production')) {
            $this->error('Perintah ini hanya untuk lingkungan lokal dengan Midtrans sandbox.');

            return self::FAILURE;
        }

        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            $this->error('MIDTRANS_SERVER_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');

        if (! in_array($status, self::STATUSES, true)) {
            $this->error('Status harus salah satu dari: '.implode(', ', self::STATUSES).'.');

            return self::FAILURE;
        }

        $code = strtoupper(trim((string) $this->argument('code')));
        $order = Order::where('code', $code)->first();

        // Boleh juga memberi ID Midtrans lengkap (ORD-XXXXXX-2) untuk memilih percobaan tertentu.
        $payment = $order
            ? $order->payments()->latest('id')->first()
            : Payment::where('midtrans_order_id', $code)->first();

        if (! $payment) {
            $this->error("Tidak ada percobaan bayar untuk \"{$code}\". Buka pembayarannya dulu dari halaman order (tombol Bayar Sekarang).");

            return self::FAILURE;
        }

        $order ??= $payment->order;

        $payload = [
            'order_id' => $payment->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => number_format($payment->gross_amount, 2, '.', ''),
            'transaction_status' => $status,
            'payment_type' => (string) $this->option('type'),
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'settlement_time' => now()->format('Y-m-d H:i:s'),
        ];
        $payload['signature_key'] = MidtransSignature::make($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $serverKey);

        $result = $handler->handle($payload);

        if ($result !== NotificationResult::Processed) {
            $this->error("Notifikasi ditolak: {$result->value}.");

            return self::FAILURE;
        }

        $order = $order->fresh();

        $this->info("Notifikasi \"{$status}\" untuk {$payment->midtrans_order_id} diproses.");
        $this->table(['Order', 'Pembayaran', 'Antrean', 'Dapur'], [[
            $order->code,
            $order->payment_status->value,
            $order->queueLabel() ?? '-',
            $order->kitchen_status->value,
        ]]);

        return self::SUCCESS;
    }
}
