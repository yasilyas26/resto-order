@php
    $config = [
        'state' => $state,
        'statusUrl' => route('order.status', $order),
        'recheckUrl' => route('order.recheck', $order),
        'expiresAt' => $state === 'waiting' ? $status['expiresAt'] : null,
        'serverTime' => $status['serverTime'],
        'instructions' => $status['instructions'],
    ];

    // Satu tempat untuk tampilan tiap hasil: ikon, warna, judul, dan penjelasan.
    $view = [
        'success' => ['icon' => 'check', 'ring' => 'bg-emerald-100 text-emerald-600', 'title' => 'Pembayaran Berhasil', 'text' => 'Terima kasih! Pesananmu sudah masuk antrean dapur dan akan segera diantar ke mejamu.'],
        'waiting' => ['icon' => 'clock', 'ring' => 'bg-amber-100 text-amber-600', 'title' => 'Menunggu Konfirmasi Pembayaran', 'text' => 'Kami sedang memeriksa pembayaranmu. Halaman ini akan berubah otomatis begitu pembayaran terkonfirmasi.'],
        'failed' => ['icon' => 'x', 'ring' => 'bg-red-100 text-red-600', 'title' => 'Pembayaran Gagal', 'text' => 'Pembayaranmu tidak berhasil dan tidak ada dana yang terpotong dari pesanan ini. Kamu bisa mencoba lagi dengan metode yang sama atau yang lain.'],
        'expired' => ['icon' => 'clock', 'ring' => 'bg-gray-200 text-gray-600', 'title' => 'Waktu Pembayaran Habis', 'text' => 'Batas waktu pembayaran sudah lewat, jadi pesanan ini belum dikirim ke dapur. Pesananmu masih tersimpan: bayar lagi untuk melanjutkan.'],
    ][$state];
    $rp = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

<x-visitor-layout :title="$view['title']" bg="bg-gray-100">
    <main x-data="paymentResult(@js($config))" class="mx-auto max-w-xl px-4 py-8 sm:py-12">
        <section class="rounded-2xl bg-white p-6 text-center shadow-sm sm:p-8" role="status" aria-live="polite">
            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full {{ $view['ring'] }}">
                <x-icon :name="$view['icon']" class="h-10 w-10" />
            </span>

            <h1 class="mt-5 text-2xl font-extrabold tracking-tight">{{ $view['title'] }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ $view['text'] }}</p>

            {{-- Nomor antrean: hanya setelah lunas --}}
            @if ($state === 'success' && $status['queue'])
                <div class="mt-6 rounded-2xl border-2 border-brand-600 p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Nomor antreanmu</div>
                    <div class="font-mono text-5xl font-extrabold tracking-wide text-brand-700">{{ $status['queue'] }}</div>
                    <div class="mt-1 text-xs text-gray-500">Tunjukkan nomor ini kalau dipanggil.</div>
                </div>
            @endif

            @if ($state === 'waiting')
                @if ($status['expiresAt'])
                    <div class="mt-5 inline-flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-2 text-amber-800" role="timer" aria-live="off">
                        <x-icon name="clock" class="h-5 w-5" />
                        <span class="text-sm font-semibold">Sisa waktu bayar</span>
                        <span class="font-mono text-2xl font-extrabold tabular-nums" :class="remaining <= 60 ? 'text-red-600' : ''" x-text="countdownLabel"></span>
                    </div>
                @endif

                {{-- Cara bayar: QR / nomor Virtual Account / kode minimarket, dari balasan Midtrans. Muncul begitu datanya ada. --}}
                <template x-if="instructions">
                    <div class="mt-6 rounded-2xl border border-gray-200 p-5 text-left">
                        <h2 class="text-center text-base font-extrabold" x-text="instructions.title"></h2>
                        <div class="mt-1 text-center text-sm text-gray-600">Total bayar <b class="text-brand-700" x-text="$rupiah(instructions.amount)"></b></div>

                        <div x-show="instructions.qr" class="mt-4 flex justify-center">
                            <div class="rounded-xl border border-gray-200 bg-white p-3">
                                <div x-show="instructions.qr?.svg" x-html="instructions.qr?.svg" class="h-56 w-56 [&>svg]:h-full [&>svg]:w-full"></div>
                                <img x-show="instructions.qr?.image" :src="instructions.qr?.image" alt="Kode QR pembayaran" class="h-56 w-56">
                            </div>
                        </div>

                        <template x-for="field in instructions.fields" :key="field.label">
                            <div class="mt-4 rounded-xl bg-gray-50 p-3">
                                <div class="text-xs text-gray-500" x-text="field.label"></div>
                                <div class="mt-1 flex items-center justify-between gap-3">
                                    <span class="break-all font-mono text-xl font-extrabold tracking-wide" x-text="field.value"></span>
                                    <button type="button" @click="copyValue(field.value)" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                                        x-text="copied === field.value ? 'Tersalin!' : 'Salin'"></button>
                                </div>
                            </div>
                        </template>

                        <a x-show="instructions.deeplink" :href="instructions.deeplink" class="mt-4 block rounded-xl bg-brand-600 px-4 py-3 text-center font-bold text-white hover:bg-brand-700">Buka Aplikasi untuk Membayar</a>

                        <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                            <template x-for="step in instructions.steps" :key="step"><li x-text="step"></li></template>
                        </ol>
                    </div>
                </template>

                <p x-show="slow" x-cloak class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
                    Belum ada konfirmasi. Kalau kamu sudah membayar, tunggu sebentar. Kalau belum, lanjutkan pembayaran di bawah.
                </p>
            @endif

            {{-- Rincian --}}
            <dl class="mt-6 space-y-2.5 rounded-xl bg-gray-50 p-4 text-left text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Kode pesanan</dt><dd class="font-bold">{{ $order->code }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Meja</dt><dd class="font-semibold">{{ $order->table?->number ?? '—' }} · {{ $order->order_type->label() }}</dd></div>
                @if ($state === 'success')
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Metode bayar</dt><dd class="font-semibold">{{ $paymentLabel ?? '—' }}</dd></div>
                    @if ($status['paidAt'])
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Dibayar pukul</dt><dd class="font-semibold">{{ $status['paidAt'] }}</dd></div>
                    @endif
                @endif
                <div class="flex justify-between gap-4 border-t border-gray-200 pt-2.5"><dt class="font-semibold">Total</dt><dd class="font-extrabold text-brand-700">{{ $rp($order->total) }}</dd></div>
            </dl>

            <ul class="mt-4 space-y-1.5 text-left text-sm text-gray-700">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3">
                        <span>{{ $item->qty }}x {{ $item->menu_name }}</span>
                        <span class="shrink-0 text-gray-500">{{ $rp($item->line_total) }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Tombol --}}
            <div class="mt-8 flex flex-col gap-2.5">
                @if ($state === 'success')
                    <a href="{{ route('order.show', $order) }}" class="rounded-xl bg-brand-600 px-4 py-3.5 text-center font-bold text-white shadow-sm hover:bg-brand-700">Lacak Pesanan</a>
                    <a href="{{ $canOrderMore ? route('menu') : route('home') }}" class="rounded-xl border border-gray-300 px-4 py-3.5 text-center font-semibold text-gray-700 hover:bg-gray-50">
                        {{ $canOrderMore ? 'Pesan Lagi' : 'Kembali ke Beranda' }}
                    </a>
                @else
                    @if ($state === 'waiting')
                        {{-- Tanpa petunjuk bayar di atas (kartu): popup dibuka lagi, waktu tidak diulang. Belum pernah menekan Bayar: pilih metode dulu. Dengan petunjuk (VA/QR/minimarket) tombol ini disembunyikan: bayarnya lewat petunjuk itu. --}}
                        {{-- Belum ada petunjuk bayar (transaksi belum tercatat): pilih metode dulu. --}}
                        <a x-show="!instructions" href="{{ route('order.checkout', [$order, 'ganti' => 1]) }}" class="rounded-xl bg-brand-600 px-4 py-3.5 text-center font-bold text-white shadow-sm hover:bg-brand-700">Pilih Metode Pembayaran</a>
                        {{-- Menonjol: kalau ada petunjuk bayar, ini satu-satunya jalan untuk berganti metode (tombol Kembali browser tidak membawa ke sini). --}}
                        <a href="{{ route('order.checkout', [$order, 'ganti' => 1]) }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border-2 border-brand-600 bg-brand-50 px-4 py-3.5 text-center font-bold text-brand-700 hover:bg-brand-100">
                            <x-icon name="refresh" class="h-5 w-5" /> Ganti Metode Pembayaran
                        </a>
                    @else
                        <a href="{{ route('order.checkout', $order) }}" class="rounded-xl bg-brand-600 px-4 py-3.5 text-center font-bold text-white shadow-sm hover:bg-brand-700">
                            {{ ['failed' => 'Coba Bayar Lagi', 'expired' => 'Bayar Lagi'][$state] }}
                        </a>
                    @endif
                    <a href="{{ route('order.show', $order) }}" class="rounded-xl border border-gray-300 px-4 py-3.5 text-center font-semibold text-gray-700 hover:bg-gray-50">Lihat Status Pesanan</a>
                @endif
            </div>
        </section>
    </main>
</x-visitor-layout>
