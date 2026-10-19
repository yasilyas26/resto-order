{{-- Isi ringkasan pesanan. Dipakai di sidebar (layar lebar) dan drawer (HP) halaman menu. --}}
<div class="flex items-start justify-between gap-3">
    <div>
        <h2 class="font-bold">Ringkasan Pesanan</h2>
        <p class="text-xs text-gray-500">Nomor Meja: <b class="text-gray-900">{{ $table->number }}</b></p>
    </div>
    @include('visitor.partials.order-type-toggle')
</div>

<div class="mt-4 border-t border-gray-100">
    <p x-show="$store.cart.items.length === 0" class="py-8 text-center text-sm text-gray-400">
        Belum ada pesanan. Ketuk tombol + pada menu untuk menambah.
    </p>

    <template x-for="item in $store.cart.items" :key="item.key">
        <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-3">
            <div class="min-w-0">
                <div class="font-semibold" x-text="item.name"></div>
                <div class="text-xs text-gray-500" x-show="item.options.length" x-text="item.options.map(o => o.name).join(', ')"></div>
                <div class="text-xs italic text-gray-400" x-show="item.note" x-text="'“' + item.note + '”'"></div>
                <div class="mt-1 text-sm font-bold text-brand-700" x-text="$rupiah($lineTotal(item))"></div>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <div class="flex items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">
                    <button type="button" class="flex h-7 w-7 items-center justify-center" @click="$store.cart.setQty(item.key, item.qty - 1)" aria-label="Kurangi">−</button>
                    <span class="w-5 text-center" x-text="item.qty"></span>
                    <button type="button" class="flex h-7 w-7 items-center justify-center" @click="$store.cart.setQty(item.key, item.qty + 1)" aria-label="Tambah">+</button>
                </div>
                <button type="button" @click="$store.cart.remove(item.key)" :aria-label="'Hapus ' + item.name" :title="'Hapus ' + item.name"
                    class="flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-600">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </template>
</div>

<div class="mt-4 space-y-2 text-sm">
    <div class="flex justify-between text-gray-600">
        <span>Subtotal (<span x-text="$store.cart.count"></span> item)</span>
        <span class="font-semibold text-gray-900" x-text="$rupiah($store.cart.subtotal)"></span>
    </div>
    <div class="flex items-end justify-between border-t border-dashed border-gray-200 pt-3">
        <span class="font-bold">Total Pembayaran</span>
        <span class="text-2xl font-extrabold text-brand-700" x-text="$rupiah($store.cart.subtotal)"></span>
    </div>
    <p class="text-xs text-gray-400">Harga sudah termasuk pajak.</p>
</div>

<div class="mt-4 flex items-start gap-2 rounded-xl bg-indigo-50 px-3 py-2.5 text-xs text-gray-600">
    <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
    <p x-show="$store.cart.orderType === 'dine_in'">Pesanan akan langsung diproses dan diantar ke <b>Meja {{ $table->number }}</b> setelah pembayaran berhasil.</p>
    <p x-show="$store.cart.orderType !== 'dine_in'" x-cloak>Pesanan dibawa pulang. Kamu mendapat <b>nomor antrean</b> setelah pembayaran berhasil.</p>
</div>

<a href="{{ route('cart') }}"
    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3.5 font-bold text-white hover:bg-brand-800"
    :class="$store.cart.count === 0 ? 'pointer-events-none opacity-50' : ''"
    :aria-disabled="$store.cart.count === 0">
    <x-icon name="cart" class="h-5 w-5" />
    Lihat Keranjang
</a>
