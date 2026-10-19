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
     * @param  array{order_type: string|OrderType, items: list<array<string, mixed>>}  $data
     *
     * $draft: order milik pelanggan ini yang belum punya pembayaran (dia kembali ke keranjang lalu checkout lagi).
     * Isinya diganti dengan keranjang terbaru, jadi tidak menumpuk order "menunggu" yang tidak pernah dibayar.
     *
     * @throws InvalidCartException
     */
    public function execute(Table $table, ?User $user, array $data, ?Order $draft = null): Order
    {
        $priced = $this->pricer->price($data['items']);
        $type = $data['order_type'] instanceof OrderType ? $data['order_type'] : OrderType::from($data['order_type']);

        return DB::transaction(function () use ($table, $user, $type, $priced, $draft) {
            $attributes = [
                'table_id' => $table->id,
                'user_id' => $user?->id,
                // Pembeli tidak mengisi nama: dikenali dari akunnya kalau login, kalau tidak dari meja atau jenis order.
                'customer_name' => $user?->name ?? ($type === OrderType::Takeaway ? 'Bawa Pulang' : "Meja {$table->number}"),
                'order_type' => $type,
                'subtotal' => $priced['subtotal'],
                'total' => $priced['subtotal'], // harga menu sudah termasuk pajak, tidak ada biaya tambahan
            ];

            if ($draft) {
                // Belum ada uang yang terlibat (tidak ada pembayaran), jadi isi lama boleh diganti. Opsi dihapus dulu (FK restrict).
                foreach ($draft->items()->get() as $old) {
                    $old->options()->delete();
                    $old->delete();
                }

                $draft->update($attributes);
                $order = $draft;
            } else {
                $order = Order::create(['code' => Order::generateCode()] + $attributes);
            }

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
