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
                                            @if ($menu->effectiveOptionGroups()->isNotEmpty())
                                                <span class="text-[11px] font-semibold text-gray-500">{{ $menu->effectiveOptionGroups()->pluck('name')->join(' · ') }}</span>
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
                    @include('visitor.partials.order-summary')
                </div>
            </aside>
        </div>
    </main>

    {{-- HP: bilah bawah membuka drawer ringkasan pesanan dari sisi kanan --}}
    <div x-data="{ open: false }"
        @keydown.escape.window="open = false"
        x-effect="document.body.classList.toggle('overflow-hidden', open)"
        class="lg:hidden">
        <div x-cloak x-show="$store.cart.count > 0"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white p-3 shadow-[0_-4px_20px_rgba(0,0,0,0.06)]">
            <button type="button" @click="open = true"
                class="flex w-full items-center justify-between rounded-xl bg-brand-700 px-5 py-3.5 font-bold text-white">
                <span class="flex items-center gap-2">
                    <x-icon name="bag" class="h-5 w-5" />
                    <span x-text="$store.cart.count + ' item'"></span>
                </span>
                <span class="flex items-center gap-2">
                    <span x-text="$rupiah($store.cart.subtotal)"></span>
                    <x-icon name="arrow-right" class="h-4 w-4" />
                </span>
            </button>
        </div>

        <div x-cloak x-show="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Ringkasan pesanan">
            <div x-show="open"
                x-transition:enter="transition-opacity ease-out duration-300 motion-reduce:transition-none"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-200 motion-reduce:transition-none"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

            <aside x-show="open"
                x-transition:enter="transition-transform ease-out duration-300 motion-reduce:transition-none"
                x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition-transform ease-in duration-200 motion-reduce:transition-none"
                x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="absolute inset-y-0 right-0 flex w-[88%] max-w-sm flex-col overflow-y-auto bg-white p-5 shadow-2xl">
                <button type="button" @click="open = false" aria-label="Tutup"
                    class="mb-3 ml-auto rounded-full p-1.5 text-gray-500 hover:bg-gray-100">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
                @include('visitor.partials.order-summary')
            </aside>
        </div>
    </div>

    @include('visitor.partials.menu-modal', ['menus' => $menusForScript])
</x-visitor-layout>
