<?php

namespace Tests\Concerns;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;

/** Order contoh untuk tes papan karyawan. Membutuhkan BuildsMenu. */
trait BuildsOrders
{
    private int $orderSeq = 0;

    /**
     * Order berisi "Mie Pedas" 2x (Level 3 + Telur, Rp38.000, kategori Mie) dan "Es Teh" 1x (Rp5.000, kategori Minuman).
     *
     * @param  array<string, mixed>  $f  hasil menuFixture()
     * @param  array<string, mixed>  $state  payment_status, kitchen_status, paid_at, completed_at, table_id, customer_name
     */
    protected function paidOrder(array $f, array $state = []): Order
    {
        $this->orderSeq++;

        $order = Order::create([
            'code' => sprintf('ORD-TEST%02d', $this->orderSeq),
            'table_id' => $state['table_id'] ?? $f['table']->id,
            'customer_name' => $state['customer_name'] ?? 'Dimas Anggara',
            'customer_contact' => 'rahasia@kontak.test',
            'order_type' => 'dine_in',
            'subtotal' => 43000,
            'total' => 43000,
        ]);

        $order->forceFill([
            'payment_status' => $state['payment_status'] ?? PaymentStatus::Paid,
            'kitchen_status' => $state['kitchen_status'] ?? KitchenStatus::Queued,
            'paid_at' => array_key_exists('paid_at', $state) ? $state['paid_at'] : now()->subMinutes(8),
            'completed_at' => $state['completed_at'] ?? null,
        ])->save();

        $mie = $order->items()->create([
            'menu_id' => $f['mie']->id, 'menu_name' => 'Mie Pedas', 'category_name' => 'Mie',
            'unit_price' => 15000, 'qty' => 2, 'note' => 'cabai dipisah', 'line_total' => 38000,
        ]);
        $mie->options()->create(['group_name' => 'Level pedas', 'option_name' => 'Level 3', 'extra_price' => 1000]);
        $mie->options()->create(['group_name' => 'Topping', 'option_name' => 'Telur', 'extra_price' => 3000]);

        $order->items()->create([
            'menu_id' => $f['esTeh']->id, 'menu_name' => 'Es Teh', 'category_name' => 'Minuman',
            'unit_price' => 5000, 'qty' => 1, 'line_total' => 5000,
        ]);

        return $order->fresh(['items.options']);
    }

    protected function secondTable(string $number = 'B02'): Table
    {
        return Table::create(['number' => $number]);
    }
}
