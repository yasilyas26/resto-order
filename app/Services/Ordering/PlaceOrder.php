<?php

namespace App\Services\Ordering;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    public function __construct(private CartPricer $pricer) {}

    /**
     * Membuat order berstatus "pending" beserta item dan opsinya. Belum ada pembayaran di sini.
     *
     * @param  array{customer_name: string, customer_contact?: ?string, order_type: string|OrderType, items: list<array<string, mixed>>}  $data
     *
     * @throws InvalidCartException
     */
    public function execute(Table $table, ?User $user, array $data): Order
    {
        $priced = $this->pricer->price($data['items']);

        return DB::transaction(function () use ($table, $user, $data, $priced) {
            $order = Order::create([
                'code' => Order::generateCode(),
                'table_id' => $table->id,
                'user_id' => $user?->id,
                'customer_name' => trim($data['customer_name']),
                'customer_contact' => filled($data['customer_contact'] ?? null) ? trim($data['customer_contact']) : null,
                'order_type' => $data['order_type'] instanceof OrderType ? $data['order_type'] : OrderType::from($data['order_type']),
                'subtotal' => $priced['subtotal'],
                'total' => $priced['subtotal'], // harga menu sudah termasuk pajak, tidak ada biaya tambahan
            ]);

            foreach ($priced['lines'] as $line) {
                // Nama dan harga disalin ke order, jadi mengubah menu besok tidak mengubah riwayat.
                $item = $order->items()->create([
                    'menu_id' => $line['menu']->id,
                    'menu_name' => $line['menu']->name,
                    'category_name' => $line['menu']->category?->name,
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'note' => $line['note'],
                    'line_total' => $line['line_total'],
                ]);

                foreach ($line['options'] as $option) {
                    $item->options()->create([
                        'group_name' => $option->group->name,
                        'option_name' => $option->name,
                        'extra_price' => $option->extra_price,
                    ]);
                }
            }

            return $order;
        });
    }
}
