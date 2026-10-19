<?php

namespace App\Services\Ordering;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Memberi nomor antrean ke order yang baru lunas: satu deret per jenis order, mulai dari 1 lagi tiap hari.
 * Harus dipanggil di dalam transaksi yang sudah mengunci order (lihat PaymentNotificationHandler).
 */
class QueueNumber
{
    public function assign(Order $order, Carbon $date): void
    {
        if ($order->queue_number !== null) {
            return;
        }

        $day = $date->copy()->timezone(config('app.timezone'))->toDateString();
        $type = $order->order_type->value;

        // Pastikan barisnya ada (aman kalau dua proses membuatnya bersamaan), lalu kunci dan naikkan.
        DB::table('queue_counters')->insertOrIgnore(['counter_date' => $day, 'order_type' => $type, 'last_number' => 0]);

        $counter = DB::table('queue_counters')
            ->where('counter_date', $day)
            ->where('order_type', $type)
            ->lockForUpdate()
            ->first();

        $next = $counter->last_number + 1;

        DB::table('queue_counters')->where('id', $counter->id)->update(['last_number' => $next]);

        $order->queue_date = $day;
        $order->queue_number = $next;
    }
}
