@php
    $th = 'px-4 py-3 text-xs font-bold uppercase tracking-wide text-gray-600';
    $tones = [
        'green' => 'border-emerald-300 bg-emerald-50 text-emerald-800',
        'amber' => 'border-amber-300 bg-amber-50 text-amber-800',
        'red' => 'border-red-300 bg-red-50 text-red-800',
        'gray' => 'border-gray-300 bg-gray-50 text-gray-700',
    ];
@endphp

<x-admin-layout title="Riwayat Transaksi" subtitle="Pantau seluruh pesanan masuk, status pembayaran Midtrans, dan rincian tiap order.">
    <div x-data="{ open: null, rows: @js($rows->values()) }">
        <form method="GET" action="{{ route('admin.transactions.index') }}" class="relative mb-4 w-full max-w-md">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari kode order, nama pemesan, atau nomor meja…"
                class="w-full rounded-xl border-gray-200 bg-white py-2.5 pl-9 text-sm focus:border-brand-500 focus:ring-brand-500">
        </form>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-blue-50/60">
                    <tr>
                        <th class="{{ $th }}">Kode Order</th>
                        <th class="{{ $th }}">Waktu</th>
                        <th class="{{ $th }}">Meja / Tipe</th>
                        <th class="{{ $th }}">Nama Pemesan</th>
                        <th class="{{ $th }}">Rincian Menu</th>
                        <th class="{{ $th }}">Total Bayar</th>
                        <th class="{{ $th }}">Metode Bayar</th>
                        <th class="{{ $th }}">Status Bayar</th>
                        <th class="{{ $th }}">Status Order</th>
                        <th class="{{ $th }}">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-bold text-brand-700">#{{ $row['code'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row['time'] }}</td>
                            <td class="px-4 py-3"><span class="rounded-lg bg-blue-100 px-2 py-1 text-xs font-semibold">{{ $row['table'] }} ({{ $row['type'] }})</span></td>
                            <td class="px-4 py-3 font-semibold">{{ $row['customer'] }}</td>
                            <td class="max-w-[220px] px-4 py-3 text-gray-700"><span class="line-clamp-2">{{ $row['summary'] }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 font-bold">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['method'] }}</td>
                            <td class="px-4 py-3"><span class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $tones[$row['payTone']] }}">{{ $row['payLabel'] }}</span></td>
                            <td class="px-4 py-3">
                                @if ($row['stageLabel'] !== '—')
                                    <span class="whitespace-nowrap rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">{{ $row['stageLabel'] }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" @click="open = rows[{{ $i }}]" class="rounded-lg border border-gray-200 bg-blue-50 px-3 py-1 text-xs font-bold hover:bg-blue-100">Detail</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada transaksi yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
                <span>Menampilkan <b>{{ $orders->firstItem() ?? 0 }}-{{ $orders->lastItem() ?? 0 }}</b> dari <b>{{ $orders->total() }}</b> transaksi</span>
                {{ $orders->links() }}
            </div>
        </div>

        {{-- Detail order --}}
        <div x-show="open" x-cloak @keydown.escape.window="open = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="open = null">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" x-show="open">
                <template x-if="open">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-extrabold" x-text="'#' + open.code"></h2>
                                <p class="text-sm text-gray-600" x-text="open.time + ' · ' + open.table + ' (' + open.type + ')'"></p>
                            </div>
                            <button type="button" @click="open = null" class="text-2xl leading-none text-gray-500 hover:text-gray-900" aria-label="Tutup">×</button>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-gray-500">Pemesan</dt><dd class="font-semibold" x-text="open.customer"></dd>
                            <dt class="text-gray-500">Nomor antrean</dt><dd class="font-semibold" x-text="open.queue || '—'"></dd>
                            <dt class="text-gray-500">Metode bayar</dt><dd class="font-semibold" x-text="open.method"></dd>
                            <dt class="text-gray-500">Status bayar</dt><dd class="font-semibold" x-text="open.payLabel"></dd>
                            <dt class="text-gray-500">Status order</dt><dd class="font-semibold" x-text="open.stageLabel"></dd>
                        </dl>
                        <ul class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <template x-for="item in open.items">
                                <li class="flex justify-between gap-3" :class="item.unavailable ? 'text-gray-400 line-through' : ''">
                                    <span>
                                        <span x-text="item.qty + 'x ' + item.name"></span>
                                        <span x-show="item.options" class="text-gray-500" x-text="'(' + item.options + ')'"></span>
                                        <span x-show="item.note" class="block text-xs text-gray-500" x-text="'Catatan: ' + item.note"></span>
                                    </span>
                                    <span class="shrink-0" x-text="'Rp ' + item.total.toLocaleString('id-ID')"></span>
                                </li>
                            </template>
                        </ul>
                        <div class="mt-4 flex justify-between border-t border-gray-100 pt-4 font-extrabold">
                            <span>Total</span><span x-text="'Rp ' + open.total.toLocaleString('id-ID')"></span>
                        </div>
                        <p x-show="open.refund" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"
                            x-text="open.refund ? (open.refund.status === 'refunded' ? 'Refund Rp ' + open.refund.amount.toLocaleString('id-ID') + ' sudah dikembalikan' + (open.refund.by ? ' oleh ' + open.refund.by : '') : 'Perlu refund Rp ' + open.refund.amount.toLocaleString('id-ID') + ' (diserahkan kasir)') : ''"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
