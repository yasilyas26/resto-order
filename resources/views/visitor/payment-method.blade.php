@php
    $config = [
        'payUrl' => route('order.pay', $order),
        'resultUrl' => route('order.result', $order),
        'selected' => $selected,
        'group' => $selectedGroup,
        'choices' => collect($groups)->map(fn ($g) => array_key_first($g['options']))->all(),
        'expiresAt' => $expiresAt,
        'serverTime' => $serverTime,
    ];
@endphp

<x-visitor-layout :title="'Pilih Metode Bayar '.$order->code" bg="bg-gray-100">
    <main x-data="paymentMethod(@js($config))" class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <a href="{{ route('order.show', $order) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 hover:text-gray-900">
            <x-icon name="arrow-right" class="h-4 w-4 rotate-180" /> Kembali ke status pesanan
        </a>

        <h1 class="mt-3 text-xl font-extrabold tracking-tight sm:text-3xl">Pilih Metode Pembayaran</h1>
        <p class="mt-1 text-sm text-gray-600">Pesanan <b>{{ $order->code }}</b> · Meja {{ $order->table?->number }}</p>

        {{-- Hitung mundur: tidak tampil sama sekali sebelum tombol Bayar ditekan; sesudahnya jalan terus walau popup ditutup --}}
        <div x-show="countdownLabel || expired" x-cloak class="mt-4 flex items-center gap-3 rounded-2xl border px-4 py-3"
            :class="countdownLabel ? (remaining <= 60 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50') : (expired ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white')" role="timer" aria-live="off">
            <x-icon name="clock" class="h-6 w-6 shrink-0" ::class="countdownLabel ? (remaining <= 60 ? 'text-red-600' : 'text-amber-600') : 'text-gray-400'" />
            <div class="min-w-0 flex-1 text-sm">
                <template x-if="countdownLabel">
                    <div>
                        <div class="font-bold" :class="remaining <= 60 ? 'text-red-700' : 'text-amber-800'">Selesaikan pembayaran sebelum waktu habis</div>
                        <div class="text-xs text-gray-600">Setelah itu pesanan ini kedaluwarsa dan tidak diteruskan ke dapur.</div>
                    </div>
                </template>
                <template x-if="!countdownLabel && expired">
                    <div>
                        <div class="font-bold text-red-700">Waktu pembayaran habis</div>
                        <div class="text-xs text-gray-600">Pesananmu masih tersimpan. Tekan Bayar untuk memulai waktu baru.</div>
                    </div>
                </template>
            </div>
            <div x-show="countdownLabel" x-cloak class="shrink-0 font-mono text-2xl font-extrabold tabular-nums sm:text-3xl" :class="remaining <= 60 ? 'text-red-600' : 'text-amber-700'" x-text="countdownLabel"></div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
            {{-- Ringkasan pesanan (kiri, lebih besar): menu, jumlah, deskripsi, level/opsi, catatan --}}
            <section class="min-w-0 rounded-2xl bg-white p-4 shadow-sm sm:p-6" aria-labelledby="ringkasan-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="ringkasan-title" class="text-lg font-extrabold tracking-tight">Ringkasan Pesanan</h2>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ $order->items->sum('qty') }} item</span>
                </div>

                <ul class="mt-4 divide-y divide-gray-100">
                    @foreach ($order->items as $item)
                        @php $menu = $item->menu; @endphp
                        <li class="flex gap-3 py-4 first:pt-0 last:pb-0 sm:gap-4">
                            @if ($menu?->image)
                                <img src="{{ $menu->imageUrl() }}" alt="" class="h-16 w-16 shrink-0 rounded-xl object-cover sm:h-24 sm:w-24">
                            @else
                                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-300 sm:h-24 sm:w-24"><x-icon name="bowl" class="h-8 w-8" /></span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-bold leading-snug">{{ $item->menu_name }}</h3>
                                        <span class="mt-0.5 inline-block rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-700">{{ $item->qty }}x &middot; Rp {{ number_format($item->unit_price, 0, ',', '.') }}</span>
                                    </div>
                                    <span class="shrink-0 font-extrabold">Rp {{ number_format($item->line_total, 0, ',', '.') }}</span>
                                </div>

                                @if (filled($menu?->description))
                                    <p class="mt-1.5 line-clamp-2 text-sm text-gray-500">{{ $menu->description }}</p>
                                @endif

                                @if ($item->options->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($item->options as $option)
                                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                                                {{ $option->group_name }}: {{ $option->option_name }}@if ($option->extra_price > 0) (+Rp {{ number_format($option->extra_price, 0, ',', '.') }})@endif
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if (filled($item->note))
                                    <p class="mt-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs text-amber-900"><b>Catatan:</b> {{ $item->note }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-5 space-y-2 border-t border-gray-100 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Subtotal</dt><dd class="font-semibold">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold text-brand-700">Rp {{ number_format($order->total, 0, ',', '.') }}</dd></div>
                    <p class="text-[11px] text-gray-500">Harga sudah termasuk pajak.</p>
                </dl>
            </section>

            {{-- Metode pembayaran (kanan), lalu tombol bayar di bawahnya --}}
            <div class="min-w-0 space-y-4 lg:sticky lg:top-6">
                {{-- Daftar metode: dropdown per kelompok --}}
                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6" aria-labelledby="metode-title">
                    <h2 id="metode-title" class="text-sm font-bold">Metode Pembayaran</h2>

                    <div class="mt-3 space-y-2.5" role="radiogroup" aria-labelledby="metode-title">
                        @foreach ($groups as $key => $group)
                            <div class="rounded-xl border-2 transition" :class="group === '{{ $key }}' ? 'border-brand-600 bg-brand-50' : 'border-gray-200 hover:border-gray-300'">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-3.5">
                                    <input type="radio" name="method_group" value="{{ $key }}" x-model="group" class="sr-only">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                        <x-icon :name="$group['icon']" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold">{{ $group['label'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $group['hint'] }}</span>
                                    </span>
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                        :class="group === '{{ $key }}' ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300'">
                                        <x-icon name="check" class="h-3 w-3" x-show="group === '{{ $key }}'" />
                                    </span>
                                </label>

                            {{-- Pilihan di dalam kelompok (bank, e-wallet): dropdown ber-logo, tampil saat kelompoknya dipilih --}}
                            <div x-show="group === '{{ $key }}'" x-cloak class="px-4 pb-4" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                                <div class="text-xs font-semibold text-gray-700" id="choice-label-{{ $key }}">{{ $group['prompt'] }}</div>
                                <div class="relative mt-1">
                                    <button type="button" @click="open = !open" aria-haspopup="listbox" :aria-expanded="open" aria-labelledby="choice-label-{{ $key }}"
                                        class="flex w-full items-center gap-3 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-left text-sm font-semibold hover:border-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                        @foreach ($group['options'] as $optionKey => $optionLabel)
                                            <span x-show="choices['{{ $key }}'] === '{{ $optionKey }}'" class="flex min-w-0 flex-1 items-center gap-3">
                                                <x-visitor.pay-logo :name="$optionKey" /> <span class="truncate">{{ $optionLabel }}</span>
                                            </span>
                                        @endforeach
                                        <svg class="h-4 w-4 shrink-0 text-gray-500 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                                    </button>

                                    <ul x-show="open" x-cloak role="listbox" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                                        @foreach ($group['options'] as $optionKey => $optionLabel)
                                            <li role="option" :aria-selected="choices['{{ $key }}'] === '{{ $optionKey }}'" @click="choices['{{ $key }}'] = '{{ $optionKey }}'; open = false"
                                                class="flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm font-semibold hover:bg-brand-50"
                                                :class="choices['{{ $key }}'] === '{{ $optionKey }}' ? 'bg-brand-50' : ''">
                                                <x-visitor.pay-logo :name="$optionKey" />
                                                <span class="flex-1">{{ $optionLabel }}</span>
                                                <x-icon name="check" class="h-4 w-4 text-brand-700" x-show="choices['{{ $key }}'] === '{{ $optionKey }}'" />
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Total Bayar</span>
                        <span class="text-2xl font-extrabold text-brand-700">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                    </div>

                    <p x-show="message" x-cloak class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert" x-text="message"></p>

                    <button type="button" @click="pay()" :disabled="busy"
                        class="mt-4 w-full rounded-xl bg-brand-600 px-4 py-4 text-base font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none"
                        x-text="busy ? 'Membuka pembayaran…' : 'Bayar Rp {{ number_format($order->total, 0, ',', '.') }}'"></button>
                </section>
            </div>
        </div>
    </main>
</x-visitor-layout>
