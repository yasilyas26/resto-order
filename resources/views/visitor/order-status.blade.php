@php
    $steps = [[1, 'Menunggu Pembayaran'], [2, 'Dibayar'], [3, 'Sedang Dibuat'], [4, 'Selesai']];
    $config = [
        'code' => $order->code,
        'table' => $order->table?->number,
        'statusUrl' => route('order.status', $order),
        'recheckUrl' => route('order.recheck', $order),
        'payUrl' => route('order.pay', $order),
        'initial' => $status,
    ];
@endphp

<x-visitor-layout :title="'Status '.$order->code" bg="bg-gray-100">
    <main x-data="orderStatus(@js($config))" class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">

        {{-- Kode + garis waktu --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Kode Transaksi Pelanggan</div>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $order->code }}</h1>
                        <button type="button" @click="copy()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                            <x-icon name="copy" class="h-3.5 w-3.5" />
                            <span x-text="copied ? 'Tersalin!' : 'Salin Kode'">Salin Kode</span>
                        </button>
                    </div>
                    <div class="mt-1 text-sm text-gray-500">
                        Meja {{ $order->table?->number }} · {{ $order->order_type->label() }} · {{ $order->created_at->translatedFormat('d M Y, H:i') }} WIB
                    </div>
                </div>

                <span class="rounded-full border px-4 py-1.5 text-sm font-bold"
                    :class="{
                        waiting: 'border-amber-300 bg-amber-50 text-amber-800',
                        paid: 'border-sky-300 bg-sky-50 text-sky-800',
                        cooking: 'border-amber-300 bg-amber-50 text-amber-800',
                        done: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        expired: 'border-gray-200 bg-gray-100 text-gray-600',
                        failed: 'border-red-200 bg-red-50 text-red-700',
                    }[status.stage]"
                    x-text="status.label">{{ $status['label'] }}</span>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-8">
                <ol class="grid grid-cols-4 gap-1 text-center">
                    @foreach ($steps as [$n, $label])
                        <li class="relative flex flex-col items-center">
                            @if ($n > 1)
                                <span class="absolute right-1/2 top-5 h-0.5 w-full -translate-y-1/2"
                                    :class="status.step !== 0 && stepState({{ $n }}) !== 'todo' ? 'bg-emerald-500' : 'bg-gray-200'"></span>
                            @endif

                            <span class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold"
                                :class="{
                                    done: 'border-emerald-500 bg-emerald-500 text-white',
                                    current: 'border-amber-400 bg-amber-400 text-white ring-4 ring-amber-100',
                                    todo: 'border-gray-200 bg-gray-100 text-gray-400',
                                    problem: 'border-red-500 bg-red-500 text-white',
                                }[stepState({{ $n }})]">
                                <x-icon name="check" class="h-5 w-5" x-show="stepState({{ $n }}) === 'done'" />
                                <x-icon name="x" class="h-5 w-5" x-show="stepState({{ $n }}) === 'problem'" x-cloak />
                                <span x-show="['current', 'todo'].includes(stepState({{ $n }}))">{{ $n }}</span>
                            </span>

                            <div class="mt-2 text-xs font-bold sm:text-sm"
                                :class="stepState({{ $n }}) === 'todo' ? 'text-gray-400' : 'text-gray-900'">{{ $n }}. {{ $label }}</div>
                            <div class="mt-0.5 hidden text-[11px] text-gray-500 sm:block" x-text="subLabel({{ $n }})"></div>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Pesan sesuai tahap --}}
            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl p-4 sm:p-5"
                :class="['expired', 'failed'].includes(status.stage) ? 'bg-red-50' : 'bg-brand-50'">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white"
                        :class="['expired', 'failed'].includes(status.stage) ? 'bg-red-500' : 'bg-brand-700'">
                        <x-icon name="clock" class="h-6 w-6" x-show="['waiting', 'paid', 'expired', 'failed'].includes(status.stage)" />
                        <x-icon name="bowl" class="h-6 w-6" x-show="status.stage === 'cooking'" x-cloak />
                        <x-icon name="check" class="h-6 w-6" x-show="status.stage === 'done'" x-cloak />
                    </span>
                    <div>
                        <div class="font-bold" x-text="status.label"></div>
                        <p class="text-sm text-gray-600" x-text="status.message">{{ $status['message'] }}</p>
                    </div>
                </div>

                <button type="button" x-show="status.payable" x-cloak @click="pay()" :disabled="busy"
                    class="rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow hover:bg-brand-700 disabled:opacity-60">
                    Bayar Sekarang
                </button>
            </div>

            {{-- Refund (diisi karyawan kalau ada item yang tidak tersedia) --}}
            <div x-show="status.refund === 'needed'" x-cloak class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <b>Ada item yang tidak tersedia.</b> Refund sebesar
                <b x-text="$rupiah(status.refundAmount)"></b> akan diberikan lewat kasir. Tunjukkan kode <b>{{ $order->code }}</b> ke kasir.
            </div>
            <div x-show="status.refund === 'refunded'" x-cloak class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                Refund sebesar <b x-text="$rupiah(status.refundAmount)"></b> sudah dikembalikan oleh kasir.
            </div>
        </section>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            {{-- Daftar item --}}
            <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><x-icon name="receipt" class="h-5 w-5 text-brand-700" /> Daftar Item Menu</h2>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ $order->items->count() }} Menu Dipesan</span>
                </div>

                <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-start gap-4 py-4 {{ $item->is_unavailable ? 'opacity-60' : '' }}">
                            <span class="flex h-9 min-w-9 items-center justify-center rounded-lg bg-indigo-50 px-1.5 text-sm font-bold text-gray-700">{{ $item->qty }}x</span>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold {{ $item->is_unavailable ? 'line-through' : '' }}">{{ $item->menu_name }}</div>
                                @if ($item->options->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach ($item->options as $option)
                                            <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800">{{ $option->option_name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($item->note)
                                    <p class="mt-1.5 text-sm italic text-gray-500">Catatan: “{{ $item->note }}”</p>
                                @endif
                                @if ($item->is_unavailable)
                                    <p class="mt-1 text-xs font-semibold text-amber-700">Tidak tersedia, akan di-refund</p>
                                @endif
                            </div>
                            <div class="font-bold">{{ \App\Support\Rupiah::format($item->line_total) }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="space-y-6">
                {{-- Rincian pembayaran --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-base font-bold sm:text-lg"><x-icon name="wallet" class="h-5 w-5 shrink-0 text-brand-700" /> Rincian Pembayaran</h2>
                        <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="status.paid ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                            x-text="status.paid ? 'Lunas' : 'Belum dibayar'">{{ $status['paid'] ? 'Lunas' : 'Belum dibayar' }}</span>
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <dt>Subtotal Pesanan</dt>
                            <dd class="font-semibold text-gray-900">{{ \App\Support\Rupiah::format($order->subtotal) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-end justify-between gap-3 border-t border-dashed border-gray-200 pt-4">
                        <div>
                            <div class="text-sm font-bold">Total Pembayaran</div>
                            <div class="text-3xl font-extrabold text-brand-700">{{ \App\Support\Rupiah::format($order->total) }}</div>
                        </div>
                        <div class="text-right text-xs text-gray-500" x-show="status.paymentLabel" x-cloak>
                            Metode Pembayaran
                            <div class="font-bold text-gray-900" x-text="status.paymentLabel"></div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Harga sudah termasuk pajak.</p>
                </section>

                {{-- Aksi cepat --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Aksi Cepat Pesanan</div>
                    <div class="mt-3 space-y-3">
                        <a href="{{ $canOrderMore ? route('menu') : route('home') }}"
                            class="block rounded-xl bg-brand-600 px-4 py-3.5 text-center text-sm font-bold text-white hover:bg-brand-700">
                            Pesan Tambahan Menu
                        </a>
                        <button type="button" @click="recheck()" :disabled="busy"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-brand-200 px-4 py-3 text-sm font-bold text-brand-700 hover:bg-brand-50 disabled:opacity-60">
                            <x-icon name="refresh" class="h-4 w-4" />
                            <span x-text="busy ? 'Memeriksa…' : 'Cek Status'">Cek Status</span>
                        </button>
                        <p class="text-center text-xs text-gray-500" x-show="message" x-text="message" x-cloak role="status"></p>
                    </div>
                    <p class="mt-4 text-xs text-gray-400">Halaman ini diperbarui otomatis tiap beberapa detik. Pembayaran yang sudah dikonfirmasi Midtrans tercatat otomatis.</p>
                </section>
            </div>
        </div>
    </main>

    @push('scripts')
        @if (filled($clientKey))
            <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
        @endif
    @endpush
</x-visitor-layout>
