@php
    $config = [
        'lookupUrl' => route('riwayat.lookup'),
        'dataUrl' => route('riwayat.data'),
        'initial' => $first['orders'] ?? null,
        'nextPage' => $first['nextPage'] ?? null,
    ];
    $initials = $user
        ? \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('')
        : null;
@endphp

<x-visitor-layout title="Riwayat Pesanan" bg="bg-gray-100">
    <main x-data="history(@js($config))" class="mx-auto grid max-w-6xl items-start gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8">

        <div class="space-y-4">
            <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                <h1 class="text-2xl font-bold">Riwayat Pesanan Anda</h1>
                <p class="mt-1 text-sm text-gray-500">Daftar transaksi pesanan makan di tempat &amp; bawa pulang terkini.</p>
            </section>

            {{-- Kerangka kartu selama riwayat dimuat --}}
            <div x-show="loading" class="space-y-4" role="status" aria-busy="true" aria-label="Memuat riwayat">
                @foreach (range(1, 3) as $i)
                    <div class="rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-2">
                                <x-skeleton class="h-5 w-32" />
                                <x-skeleton class="h-3 w-44" />
                            </div>
                            <x-skeleton class="h-7 w-24 rounded-full" />
                        </div>
                        <div class="mt-5 space-y-2.5 border-t border-gray-100 pt-4">
                            <x-skeleton class="h-4 w-3/4" />
                            <x-skeleton class="h-4 w-1/2" />
                        </div>
                        <div class="mt-5 flex items-center justify-between">
                            <x-skeleton class="h-5 w-28" />
                            <x-skeleton class="h-9 w-28 rounded-xl" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-show="!loading && orders.length === 0" x-cloak class="rounded-2xl bg-white p-10 text-center shadow-sm">
                <x-icon name="receipt" class="mx-auto h-12 w-12 text-brand-300" />
                <h2 class="mt-3 text-lg font-bold">Belum ada pesanan</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($user)
                        Pesanan yang kamu buat saat masuk dengan akunmu akan muncul di sini.
                    @else
                        Pesanan yang kamu buat dari perangkat ini akan muncul di sini.
                    @endif
                </p>
                <a href="{{ route('home') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-700 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-800">
                    Mulai Memesan <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <template x-for="order in orders" :key="order.code">
                <article class="rounded-2xl bg-white p-5 shadow-sm sm:p-6" :class="order.active ? 'border-2 border-amber-300' : 'border border-gray-200'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                                :class="order.active ? '' : '!bg-gray-100 !text-gray-500'">
                                <x-icon name="bowl" class="h-6 w-6" x-show="order.active" />
                                <x-icon name="receipt" class="h-6 w-6" x-show="!order.active" x-cloak />
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-lg font-bold" x-text="order.code"></span>
                                    <span class="rounded bg-brand-50 px-2 py-0.5 font-mono text-xs font-bold text-brand-700" x-show="order.queue" x-text="order.queue" title="Nomor antrean"></span>
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-gray-600" x-show="order.table" x-text="'Meja ' + order.table"></span>
                                </div>
                                <div class="text-xs text-gray-500" x-text="order.createdAt + ' · ' + order.orderType"></div>
                            </div>
                        </div>

                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold sm:text-sm" :class="badgeClass(order.stage)">
                            <span class="h-2 w-2 rounded-full bg-current" x-show="order.active"></span>
                            <x-icon name="check" class="h-3.5 w-3.5" x-show="order.stage === 'done'" x-cloak />
                            <span x-text="order.stageLabel"></span>
                        </span>
                    </div>

                    <div class="mt-4 gap-6 border-t border-gray-100 pt-4 sm:flex sm:items-end sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold">
                                <span x-text="order.items.length + ' Menu Dipesan:'"></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="(item, index) in order.items" :key="index">
                                    <span class="rounded-lg bg-indigo-50 px-3 py-1.5 text-sm" x-text="itemChip(item)"></span>
                                </template>
                            </div>
                            <p class="mt-2 text-sm italic text-gray-500" x-show="firstNote(order)" x-text="'Catatan: ' + firstNote(order)"></p>
                        </div>

                        <div class="mt-4 shrink-0 sm:mt-0 sm:text-right">
                            <div class="text-[11px] text-gray-500">Total Transaksi</div>
                            <div class="text-2xl font-extrabold" x-text="$rupiah(order.total)"></div>
                            <a :href="order.url" class="mt-2 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold"
                                :class="order.active ? 'bg-brand-700 text-white hover:bg-brand-800' : 'border border-gray-200 text-gray-700 hover:bg-gray-50'"
                                x-text="order.stage === 'waiting' ? 'Bayar / Lihat Status' : (order.active ? 'Lihat Detail Status' : 'Lihat Detail')"></a>
                        </div>
                    </div>
                </article>
            </template>

            <div class="flex justify-center pt-2" x-show="nextPage" x-cloak>
                <button type="button" @click="loadMore()" :disabled="loadingMore"
                    class="rounded-xl border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                    x-text="loadingMore ? 'Memuat…' : 'Muat Lebih Banyak Riwayat'"></button>
            </div>
        </div>

        <aside class="space-y-6">
            @if ($user)
                {{-- Profil pelanggan --}}
                <section class="rounded-2xl bg-white p-6 shadow-sm">
                    <div class="text-center">
                        <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-100 text-2xl font-extrabold text-brand-700 ring-4 ring-brand-50">{{ $initials }}</span>
                        <h2 class="mt-3 text-xl font-bold">{{ $user->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                    </div>

                    <div class="mt-5 rounded-xl bg-indigo-50 p-4 text-center">
                        <div class="text-3xl font-extrabold text-brand-700">{{ $paidCount }}</div>
                        <div class="text-[11px] font-semibold text-gray-500">Total Orderan</div>
                    </div>

                    @if ($user->google_id)
                        <div class="mt-5 border-t border-gray-100 pt-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-bold">Akun Tertaut</span>
                                <span class="text-[11px] font-semibold text-gray-400">Keamanan Akun</span>
                            </div>
                            <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-200 bg-indigo-50/50 p-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white shadow-sm">
                                    <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1 text-sm">
                                    <span class="block font-semibold">Akun Google</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $user->email }}</span>
                                </span>
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Aktif</span>
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 space-y-3 border-t border-gray-100 pt-5">
                            <a href="{{ route('profile.edit') }}"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold hover:bg-gray-50">
                                <x-icon name="user" class="h-4 w-4" /> Kelola Profil
                            </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-100">
                                <x-icon name="logout" class="h-4 w-4" /> Keluar dari Akun
                            </button>
                        </form>
                    </div>
                </section>
            @else
                {{-- Ajakan login untuk tamu --}}
                <section class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold">Simpan Riwayat Pesanan</h2>
                    <p class="mt-2 text-sm text-gray-500">
                        Riwayat tamu hanya tersimpan di perangkat ini. Masuk atau buat akun (bisa lewat Google) supaya
                        pesananmu tercatat di semua perangkat.
                    </p>
                    <a href="{{ route('login') }}"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
                        Masuk atau Daftar <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <p class="mt-3 text-xs text-gray-400">Pesanan yang dibuat sebelum login tidak dipindahkan ke akunmu.</p>
                </section>

                <section class="rounded-2xl bg-indigo-50 p-6">
                    <h2 class="text-lg font-bold">Panduan Pesanan Tamu</h2>
                    <ul class="mt-4 space-y-3 text-sm text-gray-600">
                        <li class="flex gap-3"><x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-brand-700" /> Status pesanan diperbarui otomatis selama halaman ini terbuka.</li>
                        <li class="flex gap-3"><x-icon name="trash" class="mt-0.5 h-4 w-4 shrink-0 text-brand-700" /> Membersihkan riwayat browser atau cache akan menghapus daftar pesanan di perangkat ini.</li>
                        <li class="flex gap-3"><x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-brand-700" /> Butuh bantuan kasir? Sebutkan kode pesananmu (contoh: ORD-7F3K9Q).</li>
                    </ul>
                </section>
            @endif
        </aside>
    </main>
</x-visitor-layout>
