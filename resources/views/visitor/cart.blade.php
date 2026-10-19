<x-visitor-layout title="Keranjang" bg="bg-gray-100">
    <main x-data="checkout({ url: @js(route('checkout.store')) })"
        class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">

        {{-- Keranjang kosong --}}
        <div x-show="$store.cart.items.length === 0" x-cloak class="mx-auto max-w-lg rounded-2xl bg-white p-10 text-center shadow-sm">
            <x-icon name="bag" class="mx-auto h-12 w-12 text-brand-300" />
            <h1 class="mt-4 text-xl font-bold">Keranjangmu masih kosong</h1>
            <p class="mt-1 text-gray-500">Pilih menu favoritmu dulu, lalu kembali ke sini untuk membayar.</p>
            <a href="{{ route('menu') }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white hover:bg-brand-800">
                Lihat Menu <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div x-show="$store.cart.items.length > 0" x-cloak class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                {{-- Isi keranjang --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-bold">Keranjang Pesanan (Meja {{ $table->number }})</h1>
                            <p class="text-sm text-gray-500">Silakan periksa kembali rincian pesananmu.</p>
                        </div>
                        <span class="rounded-full bg-brand-100 px-3 py-1 text-xs font-bold text-brand-700" x-text="$store.cart.items.length + ' Menu Terpilih'"></span>
                    </div>

                    <div class="mt-5 border-t border-gray-100">
                        <template x-for="item in $store.cart.items" :key="item.key">
                            <div class="flex gap-4 border-b border-gray-100 py-5">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-brand-50 sm:h-24 sm:w-24">
                                    <template x-if="item.image">
                                        <img :src="item.image" :alt="item.name" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!item.image">
                                        <div class="flex h-full items-center justify-center text-brand-300"><x-icon name="bowl" class="h-8 w-8" /></div>
                                    </template>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-bold" x-text="item.name"></h2>
                                        <span x-show="item.category" x-text="item.category"
                                            class="rounded bg-brand-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-700"></span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500" x-show="item.options.length">
                                        Pilihan: <b class="text-gray-800" x-text="item.options.map(o => o.name).join(', ')"></b>
                                    </p>
                                    <p class="mt-1.5 inline-block max-w-full rounded-lg bg-brand-50 px-2.5 py-1 text-xs italic text-brand-800" x-show="item.note"
                                        x-text="'Catatan: “' + item.note + '”'"></p>
                                    <div class="mt-2 flex items-center gap-4 text-xs font-semibold">
                                        <button type="button" class="inline-flex items-center gap-1 text-brand-700 hover:underline"
                                            @click="$dispatch('open-menu', { id: item.menuId, key: item.key })">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" /> Ubah
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1 text-brand-700 hover:underline"
                                            @click="$store.cart.remove(item.key)">
                                            <x-icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                        </button>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col items-end justify-between">
                                    <div class="font-bold text-brand-700" x-text="$rupiah($lineTotal(item))"></div>
                                    <div class="flex items-center rounded-full border border-brand-200 text-sm font-bold">
                                        <button type="button" class="flex h-8 w-8 items-center justify-center text-brand-700" @click="$store.cart.setQty(item.key, item.qty - 1)" aria-label="Kurangi">−</button>
                                        <span class="w-6 text-center" x-text="item.qty"></span>
                                        <button type="button" class="flex h-8 w-8 items-center justify-center text-brand-700" @click="$store.cart.setQty(item.key, item.qty + 1)" aria-label="Tambah">+</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <a href="{{ route('menu') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-100">
                        <x-icon name="plus" class="h-4 w-4" /> Tambah Menu Lain
                    </a>
                </section>

                {{-- Tanpa form nama: Makan di Tempat otomatis memakai nomor meja, Bawa Pulang memakai nomor antrean setelah bayar. --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div x-show="$store.cart.orderType === 'dine_in'" class="flex items-center gap-3 rounded-xl bg-brand-50 p-4">
                        <x-icon name="utensils" class="h-5 w-5 shrink-0 text-brand-700" />
                        <p class="text-sm">Pesanan diantar ke <b>Meja {{ $table->number }}</b>.</p>
                    </div>
                    <div x-show="$store.cart.orderType !== 'dine_in'" x-cloak class="flex items-center gap-3 rounded-xl bg-brand-50 p-4">
                        <x-icon name="bag" class="h-5 w-5 shrink-0 text-brand-700" />
                        <p class="text-sm">Pesanan dibawa pulang. Kamu mendapat <b>nomor antrean</b> setelah pembayaran berhasil.</p>
                    </div>

                    <div class="mt-4 flex gap-3 rounded-xl bg-indigo-50 p-4">
                        <x-icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-brand-700" />
                        <div class="text-sm">
                            <div class="font-bold">Pembayaran Aman</div>
                            <p class="text-gray-600">
                                Pembayaran diproses oleh Midtrans. Bayar dalam {{ config('midtrans.expiry_minutes') }} menit setelah pesanan dibuat,
                                dan pesananmu langsung masuk ke dapur begitu pembayaran dikonfirmasi.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6 lg:sticky lg:top-24">
                {{-- Ringkasan biaya --}}
                <section class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between gap-3 border-b border-brand-100 pb-4">
                        <h2 class="text-lg font-bold">Ringkasan Biaya</h2>
                        @include('visitor.partials.order-type-toggle')
                    </div>

                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal (<span x-text="$store.cart.count"></span> porsi)</span>
                            <span class="font-semibold text-gray-900" x-text="$rupiah($store.cart.subtotal)"></span>
                        </div>
                        <p class="text-xs text-gray-400">Harga sudah termasuk pajak.</p>
                    </div>

                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <div class="text-sm font-bold">Total Pembayaran</div>
                        <div class="text-4xl font-extrabold text-brand-700" x-text="$rupiah($store.cart.subtotal)"></div>
                    </div>

                    <div x-show="errors.length" x-cloak class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <template x-for="message in errors" :key="message">
                            <p x-text="message"></p>
                        </template>
                    </div>

                    <button type="button" @click="submit()" :disabled="!canSubmit"
                        class="mt-5 w-full rounded-xl bg-brand-600 px-4 py-4 text-base font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                        x-text="loading ? 'Memproses…' : 'Bayar Sekarang - ' + $rupiah($store.cart.subtotal)"></button>
                </section>

                {{-- Metode pembayaran: dipilih di jendela Midtrans, jadi di sini hanya informasi --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-sm font-bold">Metode Pembayaran</h2>
                    <p class="mt-1 text-xs text-gray-500">Pilih metode di jendela pembayaran setelah menekan tombol bayar.</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ([
                            ['QRIS', 'Semua e-wallet: Dana, OVO, ShopeePay, dan lainnya', 'qr'],
                            ['GoPay', 'Buka aplikasi atau scan barcode', 'wallet'],
                            ['Transfer Virtual Account', 'BCA, Mandiri, BRI, BNI', 'bank'],
                        ] as [$name, $desc, $icon])
                            <li class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-5 w-5" /></span>
                                <span>
                                    <span class="block font-semibold">{{ $name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $desc }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>
    </main>

    @include('visitor.partials.menu-modal', ['menus' => $menusForScript])

    @push('scripts')
        @if (filled($clientKey))
            <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
        @endif
    @endpush
</x-visitor-layout>
