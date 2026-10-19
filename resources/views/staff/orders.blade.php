@php
    $config = [
        'dataUrl' => route('staff.orders.data'),
        'baseUrl' => url('/staff/orders'),
        'initial' => $initial,
        'pollMs' => 5000,
    ];
    $filters = [['all', 'Semua'], ['queued', 'Belum dibuat'], ['cooking', 'Sedang dibuat'], ['done', 'Selesai']];
@endphp

<x-staff-layout title="Pesan Aktif">
    <main x-data="board(@js($config))" class="mx-auto max-w-[1600px] px-4 pb-12 sm:px-6">

        {{-- Saringan status + penanda sinkronisasi --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 py-4">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($filters as [$key, $label])
                    <button type="button" @click="filter = '{{ $key }}'"
                        class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold transition"
                        :class="filter === '{{ $key }}' ? 'border-brand-700 bg-brand-700 text-white' : 'border-brand-200 bg-white text-brand-700 hover:bg-brand-50'">
                        {{ $label }}
                        <span class="rounded px-1.5 text-[11px] font-bold"
                            :class="filter === '{{ $key }}' ? 'bg-white/25 text-white' : 'bg-brand-100 text-brand-700'"
                            x-text="counts['{{ $key }}']"></span>
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2 text-xs" :class="offline ? 'text-red-600' : 'text-gray-500'" role="status">
                <span class="h-2 w-2 rounded-full" :class="offline ? 'bg-red-500' : 'bg-emerald-500'"></span>
                <span x-show="!offline">Diperbarui otomatis · <span x-text="syncedLabel"></span></span>
                <span x-show="offline" x-cloak>Koneksi terputus, mencoba lagi…</span>
            </div>
        </div>

        <div x-show="error" x-cloak class="mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <span class="flex-1" x-text="error"></span>
            <button type="button" @click="error = ''" class="font-bold" aria-label="Tutup">×</button>
        </div>

        <div x-show="visible.length === 0" x-cloak class="mx-auto mt-16 max-w-md text-center text-gray-500">
            <x-icon name="receipt" class="mx-auto h-12 w-12 text-brand-300" />
            <h2 class="mt-3 text-lg font-bold text-gray-800">Belum ada order di sini</h2>
            <p class="mt-1 text-sm">Order yang sudah dibayar pelanggan akan muncul otomatis dalam beberapa detik.</p>
        </div>

        {{-- Dikelompokkan per status, ditumpuk dari atas: Belum dibuat, Sedang dibuat, lalu Selesai. Kelompok kosong disembunyikan. --}}
        @foreach ([
            ['queued', 'Belum Dibuat', 'Menunggu dimasak', 'bg-amber-400'],
            ['cooking', 'Sedang Dibuat', 'Sedang di dapur', 'bg-sky-500'],
            ['done', 'Selesai', 'Sudah selesai dimasak', 'bg-slate-400'],
        ] as [$kind, $title, $hint, $dot])
        <section x-show="group('{{ $kind }}').length > 0" x-cloak class="mt-7 first:mt-5" aria-labelledby="grup-{{ $kind }}">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 pb-3">
                <span class="h-3 w-3 rounded-full {{ $dot }}"></span>
                <h2 id="grup-{{ $kind }}" class="text-lg font-extrabold tracking-tight">{{ $title }}</h2>
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-gray-700" x-text="group('{{ $kind }}').length"></span>
                <span class="text-xs text-gray-500">{{ $hint }}</span>
            </div>

        <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <template x-for="order in group('{{ $kind }}')" :key="order.code">
                <article class="flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm"
                    :class="order.kitchen === 'done' ? 'border-slate-200' : 'border-brand-200'">

                    {{-- Kepala kartu: meja, pemesan, kode, waktu tunggu --}}
                    <div class="p-4" :class="order.kitchen === 'done' ? 'bg-slate-50' : 'bg-gradient-to-br from-brand-50 via-white to-white'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span x-show="order.queue" class="rounded-xl border-2 bg-white px-3 py-1 font-mono text-xl font-extrabold tracking-wide"
                                    :class="order.kitchen === 'done' ? 'border-slate-300 text-slate-700' : 'border-brand-600 text-brand-700'"
                                    x-text="order.queue" title="Nomor antrean"></span>
                                <span x-show="order.table" class="rounded-xl px-3 py-1.5 text-xl font-extrabold uppercase text-white"
                                    :class="order.kitchen === 'done' ? 'bg-slate-800' : 'bg-brand-700'"
                                    x-text="'Meja ' + order.table"></span>
                                <span x-show="order.sequence > 1" x-text="'Order ke-' + order.sequence"
                                    class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800"></span>
                            </div>
                            <div class="min-w-0 text-right">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500" x-text="order.orderType"></div>
                                <div class="truncate font-bold" x-text="order.customer"></div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                            <span class="font-mono text-[11px] text-gray-500" x-text="'#' + order.code"></span>

                            <div class="flex items-center gap-2">
                                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold"
                                    :class="{
                                        queued: 'border-amber-300 bg-amber-50 text-amber-800',
                                        cooking: 'border-sky-300 bg-sky-50 text-sky-800',
                                        done: 'border-slate-300 bg-white text-slate-600',
                                    }[order.kitchen]"
                                    x-text="statusLabels[order.kitchen]"></span>

                                <span x-show="ready(order)" class="rounded-full border border-brand-500 bg-white px-2.5 py-0.5 text-[11px] font-bold text-brand-700">Siap Antar</span>

                                <span x-show="order.kitchen !== 'done'" class="flex items-center gap-1 text-[11px]" :class="waitClass(order)">
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    <span x-text="waitText(order)"></span>
                                </span>
                                <span x-show="order.kitchen === 'done'" class="text-[11px] text-gray-500" x-text="'Selesai ' + (order.completedAtLabel || '')"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Peringatan refund --}}
                    <div x-show="order.refund === 'needed'" x-cloak class="flex items-center gap-2 border-y border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900">
                        <x-icon name="alert" class="h-4 w-4 shrink-0" />
                        <span>Perlu refund <span x-text="rupiah(order.refundAmount)"></span> · diserahkan kasir di tab Riwayat &amp; Refund</span>
                    </div>
                    <div x-show="order.refund === 'refunded'" x-cloak class="flex items-center gap-2 border-y border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800">
                        <x-icon name="check" class="h-4 w-4 shrink-0" />
                        <span>Refund <span x-text="rupiah(order.refundAmount)"></span> sudah dikembalikan</span>
                    </div>

                    {{-- Daftar sajian --}}
                    <div class="flex-1 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-800">Daftar Sajian (Centang saat dibawa):</h3>
                            <span class="shrink-0 rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-gray-600"
                                x-text="order.servedCount + '/' + order.servableCount + ' Diantar'"></span>
                        </div>

                        <ul class="mt-3 space-y-2">
                            <template x-for="item in order.items" :key="item.id">
                                <li class="flex items-start gap-3 rounded-xl border px-3 py-2.5"
                                    :class="item.unavailable ? 'border-amber-200 bg-amber-50/60' : 'border-indigo-100 bg-indigo-50/50'">

                                    <button type="button" role="checkbox" :aria-checked="item.served"
                                        @click="toggleServed(order, item)"
                                        :disabled="!!(order.kitchen === 'done' || item.unavailable || busy[order.code])"
                                        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border-2 transition disabled:cursor-not-allowed"
                                        :class="item.served ? (order.kitchen === 'done' ? 'border-slate-700 bg-slate-700 text-white' : 'border-brand-700 bg-brand-700 text-white') : (item.unavailable ? 'border-amber-300 bg-white opacity-50' : 'border-brand-300 bg-white hover:border-brand-600')"
                                        :aria-label="'Centang ' + item.name">
                                        <x-icon name="check" class="h-4 w-4" x-show="item.served" />
                                    </button>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="font-bold leading-snug"
                                                :class="item.served || item.unavailable ? 'text-gray-500 line-through' : 'text-brand-700'"
                                                x-text="item.qty + 'x ' + item.name + (item.options.length ? ' ' + item.options.join(', ') : '')"></div>
                                            <span x-show="item.station" x-text="item.station"
                                                class="shrink-0 rounded bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-600 ring-1 ring-gray-200"></span>
                                        </div>
                                        <div class="mt-0.5 text-xs" :class="item.served ? 'text-gray-500' : 'text-brand-600'" x-show="item.note" x-text="'Catatan: ' + item.note"></div>
                                        <div class="mt-0.5 text-xs font-semibold text-amber-700" x-show="item.unavailable">Tidak tersedia · akan di-refund</div>
                                    </div>

                                    <button type="button" x-show="canMarkUnavailable(order)" :disabled="!!busy[order.code]"
                                        @click="toggleUnavailable(order, item)"
                                        class="shrink-0 text-[11px] font-semibold text-amber-700 underline decoration-dotted hover:text-amber-900 disabled:opacity-50"
                                        x-text="item.unavailable ? 'Batal' : 'Habis'"></button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Tombol aksi --}}
                    <div class="border-t border-gray-100 bg-slate-50/70 p-3">
                        <button type="button" x-show="order.kitchen === 'queued'" @click="start(order)" :disabled="!!busy[order.code]"
                            class="w-full rounded-xl bg-brand-700 px-4 py-3.5 font-bold text-white hover:bg-brand-800 disabled:opacity-60">Mulai Buat</button>

                        <button type="button" x-show="order.kitchen === 'cooking'" x-cloak @click="complete(order)" :disabled="!!busy[order.code]"
                            class="w-full rounded-xl bg-brand-700 px-4 py-3.5 font-bold text-white hover:bg-brand-800 disabled:opacity-60">Tandai Selesai</button>

                        <div x-show="order.kitchen === 'done'" x-cloak
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-100/70 px-4 py-3.5 font-bold text-slate-700">
                            <x-icon name="check" class="h-4 w-4" /> Selesai
                        </div>
                    </div>
                </article>
            </template>
        </div>
        </section>
        @endforeach
    </main>
</x-staff-layout>
