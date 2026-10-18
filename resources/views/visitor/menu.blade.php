<x-visitor-layout title="Menu">
    <main x-data class="mx-auto max-w-7xl px-4 pb-32 pt-6 sm:px-6 lg:px-8 lg:pb-14">

        {{-- Banner meja aktif (bisa ditutup untuk sesi ini) --}}
        <div x-data="{ show: (() => { try { return sessionStorage.getItem('resto.banner') !== '1'; } catch (e) { return true; } })() }"
            x-show="show" x-cloak
            class="flex items-center gap-4 rounded-2xl border border-brand-200 bg-brand-100 p-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-700 text-white">
                <x-icon name="utensils" class="h-6 w-6" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="font-bold">
                    Halo! Kamu sedang duduk di Meja {{ $table->number }}
                    <span class="ml-1 rounded-full bg-brand-700 px-2 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-white">Aktif</span>
                </div>
                <div class="text-sm text-brand-800">Pilih menu favoritmu, pesanan akan diantar ke meja ini.</div>
            </div>
            <button type="button" aria-label="Tutup"
                @click="show = false; try { sessionStorage.setItem('resto.banner', '1'); } catch (e) {}"
                class="rounded-full p-1.5 text-brand-700 hover:bg-brand-200">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]">
            {{-- Daftar menu per kategori --}}
            <div>
                @forelse ($categories as $category)
                    <section id="kategori-{{ $category->id }}" class="mb-10">
                        <div class="mb-4 flex items-center gap-2.5 border-b border-gray-200 pb-3">
                            <x-icon name="bowl" class="h-6 w-6 text-brand-700" />
                            <h2 class="text-2xl font-bold">{{ $category->name }}</h2>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($category->menus as $menu)
                                @php($soldOut = ! $menu->is_available)
                                <article class="relative flex min-h-[12rem] justify-between gap-3 rounded-2xl border p-4 {{ $soldOut ? 'border-gray-200 bg-indigo-50/50' : 'border-gray-200 bg-white shadow-sm transition hover:shadow-md' }}">
                                    <div class="flex min-w-0 flex-col">
                                        <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                                            @if ($soldOut)
                                                <span class="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white">Habis</span>
                                            @endif
                                            @if ($menu->optionGroups->isNotEmpty())
                                                <span class="text-[11px] font-semibold text-gray-500">{{ $menu->optionGroups->pluck('name')->join(' · ') }}</span>
                                            @endif
                                        </div>
                                        <h3 class="text-lg font-bold leading-snug {{ $soldOut ? 'text-gray-400' : '' }}">{{ $menu->name }}</h3>
                                        @if ($menu->description)
                                            <p class="mt-1 line-clamp-2 text-sm {{ $soldOut ? 'text-gray-400' : 'text-gray-500' }}">{{ $menu->description }}</p>
                                        @endif
                                        <div class="mt-auto pt-3 text-xl font-extrabold {{ $soldOut ? 'text-gray-400' : 'text-brand-700' }}">{{ \App\Support\Rupiah::format($menu->price) }}</div>
                                    </div>

                                    <div class="relative h-24 w-24 shrink-0 sm:h-28 sm:w-28">
                                        @if ($menu->image)
                                            <img src="{{ $menu->imageUrl() }}" alt="{{ $menu->name }}" loading="lazy"
                                                class="h-full w-full rounded-xl object-cover {{ $soldOut ? 'grayscale' : '' }}">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center rounded-xl bg-brand-50 text-brand-300">
                                                <x-icon name="bowl" class="h-10 w-10" />
                                            </div>
                                        @endif
                                        @if ($soldOut)
                                            <span class="absolute inset-x-2 top-1/2 -translate-y-1/2 rounded bg-brand-600/90 py-1 text-center text-[11px] font-bold text-white">Stok Habis</span>
                                        @endif
                                    </div>

                                    @unless ($soldOut)
                                        <button type="button" @click="$dispatch('open-menu', { id: {{ $menu->id }} })"
                                            class="absolute bottom-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-brand-700 text-white shadow-md hover:bg-brand-800"
                                            aria-label="Pilih {{ $menu->name }}">
                                            <x-icon name="plus" class="h-5 w-5" />
                                        </button>
                                    @endunless
                                </article>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                        Menu belum tersedia. Silakan hubungi pelayan.
                    </div>
                @endforelse
            </div>

            {{-- Ringkasan pesanan (layar lebar) --}}
            <aside class="hidden lg:block">
                <div x-data class="sticky top-24 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
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
                                <div class="flex shrink-0 items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">
                                    <button type="button" class="flex h-7 w-7 items-center justify-center" @click="$store.cart.setQty(item.key, item.qty - 1)" aria-label="Kurangi">−</button>
                                    <span class="w-5 text-center" x-text="item.qty"></span>
                                    <button type="button" class="flex h-7 w-7 items-center justify-center" @click="$store.cart.setQty(item.key, item.qty + 1)" aria-label="Tambah">+</button>
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
                        <p>Pesanan akan langsung diproses dan diantar ke <b>Meja {{ $table->number }}</b> setelah pembayaran berhasil.</p>
                    </div>

                    <a href="{{ route('cart') }}"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3.5 font-bold text-white hover:bg-brand-800"
                        :class="$store.cart.count === 0 ? 'pointer-events-none opacity-50' : ''"
                        :aria-disabled="$store.cart.count === 0">
                        Kirim Pesanan Sekarang
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </aside>
        </div>
    </main>

    {{-- Bilah bawah untuk HP: ringkas, membuka halaman keranjang --}}
    <div x-data x-cloak x-show="$store.cart.count > 0"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white p-3 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] lg:hidden">
        <a href="{{ route('cart') }}" class="flex items-center justify-between rounded-xl bg-brand-700 px-5 py-3.5 font-bold text-white">
            <span class="flex items-center gap-2">
                <x-icon name="bag" class="h-5 w-5" />
                <span x-text="$store.cart.count + ' item'"></span>
            </span>
            <span class="flex items-center gap-2">
                <span x-text="$rupiah($store.cart.subtotal)"></span>
                <x-icon name="arrow-right" class="h-4 w-4" />
            </span>
        </a>
    </div>

    @include('visitor.partials.menu-modal', ['menus' => $menusForScript])
</x-visitor-layout>
