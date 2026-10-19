@php
    $config = [
        'dataUrl' => route('staff.history.data'),
        'refundBase' => url('/staff/riwayat'),
        'initial' => $initial,
        'filter' => $filter,
    ];
    $filters = [['refund_needed', 'Perlu Refund'], ['refunded', 'Sudah Dikembalikan'], ['done', 'Semua Selesai']];
@endphp

<x-staff-layout title="Riwayat & Refund">
    <main x-data="refundHistory(@js($config))" class="mx-auto max-w-5xl px-4 pb-12 sm:px-6">

        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 py-4">
            @foreach ($filters as [$key, $label])
                <button type="button" @click="setFilter('{{ $key }}')"
                    class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold transition"
                    :class="filter === '{{ $key }}' ? 'border-brand-700 bg-brand-700 text-white' : 'border-brand-200 bg-white text-brand-700 hover:bg-brand-50'">
                    {{ $label }}
                    <span class="rounded px-1.5 text-[11px] font-bold"
                        :class="filter === '{{ $key }}' ? 'bg-white/25 text-white' : 'bg-brand-100 text-brand-700'"
                        x-text="counts['{{ $key }}']"></span>
                </button>
            @endforeach
        </div>

        <div x-show="error" x-cloak class="mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <span class="flex-1" x-text="error"></span>
            <button type="button" @click="error = ''" class="font-bold" aria-label="Tutup">×</button>
        </div>

        <p x-show="!loading && orders.length === 0" x-cloak class="mt-16 text-center text-sm text-gray-500">
            <span x-show="filter === 'refund_needed'">Tidak ada refund yang menunggu. 🎉</span>
            <span x-show="filter === 'refunded'">Belum ada refund yang dikembalikan.</span>
            <span x-show="filter === 'done'">Belum ada order yang selesai.</span>
        </p>

        {{-- Kerangka kartu saat daftar belum ada isinya dan sedang dimuat (ganti saringan) --}}
        <ul x-show="loading && orders.length === 0" class="mt-5 space-y-3" role="status" aria-busy="true" aria-label="Memuat riwayat">
            @foreach (range(1, 3) as $i)
                <li class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-skeleton class="h-9 w-24 rounded-xl" />
                            <div class="space-y-2"><x-skeleton class="h-4 w-32" /><x-skeleton class="h-3 w-24" /></div>
                        </div>
                        <div class="space-y-2 text-right"><x-skeleton class="ml-auto h-3 w-20" /><x-skeleton class="ml-auto h-5 w-24" /></div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2"><x-skeleton class="h-3 w-40" /><x-skeleton class="h-3 w-36" /></div>
                </li>
            @endforeach
        </ul>

        <ul class="mt-5 space-y-3">
            <template x-for="order in orders" :key="order.code">
                <li class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5" :class="order.refund === 'needed' ? 'border-amber-300' : 'border-gray-200'">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span x-show="order.queue" class="rounded-xl border-2 border-slate-300 bg-white px-3 py-0.5 font-mono text-lg font-extrabold text-slate-700" x-text="order.queue" title="Nomor antrean"></span>
                            <span x-show="order.table" class="rounded-xl bg-slate-800 px-3 py-1 text-lg font-extrabold uppercase text-white" x-text="'Meja ' + order.table"></span>
                            <div>
                                <div class="font-bold" x-text="order.customer"></div>
                                <div class="font-mono text-[11px] text-gray-500" x-text="'#' + order.code"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] text-gray-500">Total pesanan</div>
                            <div class="text-lg font-extrabold" x-text="rupiah(order.total)"></div>
                        </div>
                    </div>

                    <dl class="mt-3 grid gap-x-6 gap-y-1 text-xs text-gray-500 sm:grid-cols-2">
                        <div>Dibayar: <span class="font-semibold text-gray-700" x-text="order.paidAtLabel"></span></div>
                        <div x-show="order.completedAtLabel">Selesai: <span class="font-semibold text-gray-700" x-text="order.completedAtLabel"></span></div>
                    </dl>

                    {{-- Refund --}}
                    <div x-show="order.refund !== 'none'" x-cloak class="mt-4 rounded-xl border px-4 py-3"
                        :class="order.refund === 'needed' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-sm">
                                <div class="font-bold" :class="order.refund === 'needed' ? 'text-amber-900' : 'text-emerald-800'">
                                    <span x-show="order.refund === 'needed'">Perlu refund </span>
                                    <span x-show="order.refund === 'refunded'">Sudah dikembalikan </span>
                                    <span x-text="rupiah(order.refundAmount)"></span>
                                </div>
                                <div class="text-xs text-gray-600" x-show="order.unavailableItems.length"
                                    x-text="'Item tidak tersedia: ' + order.unavailableItems.join(', ')"></div>
                                <div class="mt-0.5 text-xs text-emerald-800" x-show="order.refund === 'refunded'"
                                    x-text="'Oleh ' + (order.refundedBy || '—') + ' · ' + (order.refundedAtLabel || '')"></div>
                            </div>

                            <button type="button" x-show="order.refund === 'needed'" @click="markRefunded(order)" :disabled="!!busy[order.code]"
                                class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60">
                                Tandai Sudah Dikembalikan
                            </button>
                        </div>
                    </div>
                </li>
            </template>
        </ul>

        <div class="mt-6 flex justify-center" x-show="nextPage" x-cloak>
            <button type="button" @click="load(false)" :disabled="loading"
                class="rounded-xl border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                x-text="loading ? 'Memuat…' : 'Muat Lebih Banyak'"></button>
        </div>
    </main>
</x-staff-layout>
