@php
    $config = ['dataUrl' => route('admin.dashboard.data'), 'initial' => $initial, 'pollMs' => 5000];
    $columns = [['queued', 'Belum Dibuat', 'Antrean Awal'], ['cooking', 'Sedang Dibuat', 'Proses Dapur'], ['done', 'Selesai', 'Siap Diantar']];
    $types = [['all', 'Semua Meja'], ['dine_in', 'Dine-in Saja'], ['takeaway', 'Takeaway']];
@endphp

<x-admin-layout title="Performa Hari Ini" subtitle="Pembaruan real-time stasiun kasir dan dapur">
    <div x-data="adminDashboard(@js($config))">
        <p class="-mt-4 mb-4 text-right text-[11px] font-bold text-gray-700">
            <span x-show="!offline">Terakhir diperbarui: <span x-text="stats.updatedAt"></span></span>
            <span x-show="offline" x-cloak class="text-red-600">Koneksi terputus, mencoba lagi…</span>
        </p>

        {{-- Grafik omzet harian --}}
        <section class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" aria-labelledby="omzet-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="omzet-title" class="text-lg font-extrabold tracking-tight">Omzet Harian</h2>
                    <p class="text-sm text-gray-600">Omzet <span x-text="unitLabel"></span>, sudah dikurangi refund</p>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-600">Total rentang ini</div>
                    <div class="text-xl font-extrabold" x-text="rupiah(chartTotal)"></div>
                </div>
            </div>

            {{-- Pilihan rentang waktu --}}
            <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                Rentang
                <select @change="setRange($event.target.value)" aria-label="Rentang waktu grafik"
                    class="rounded-xl border-gray-200 bg-white py-1.5 pl-3 pr-8 text-sm font-semibold focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($ranges as $key => $label)
                        <option value="{{ $key }}" @selected($key === $defaultRange)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div x-ref="chart" class="relative mt-4 transition-opacity" :class="loading ? 'opacity-50' : ''" @mouseleave="hover = null">
                {{-- Isi digambar dari JS (svgMarkup): Alpine tidak bisa x-for di dalam <svg>. --}}
                <svg :width="chartW" :height="chartH" :viewBox="`0 0 ${chartW} ${chartH}`" class="block max-w-full" role="img" aria-label="Grafik omzet"
                    @mouseover="onChartMove($event)" x-html="svgMarkup"></svg>

                <div x-show="hover !== null" x-cloak
                    class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg"
                    :style="`left: ${tooltipLeft}px`">
                    <div class="font-semibold text-gray-600" x-text="hover === null ? '' : series[hover].full"></div>
                    <div class="text-sm font-extrabold" x-text="hover === null ? '' : rupiah(series[hover].revenue)"></div>
                </div>
            </div>
        </section>

        {{-- Tiga angka hari ini --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between text-sm text-gray-700">Omzet Hari Ini <x-icon name="bank" class="h-5 w-5 text-gray-500" /></div>
                <div class="mt-6 text-3xl font-extrabold tracking-tight" x-text="rupiah(stats.revenue)"></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between text-sm text-gray-700">Pesanan Selesai <x-icon name="check" class="h-5 w-5 text-gray-500" /></div>
                <div class="mt-6 text-3xl font-extrabold tracking-tight"><span x-text="stats.completed"></span> <span class="text-base font-medium text-gray-600">Pesanan</span></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between text-sm text-gray-700">Rata-rata Waktu Masak <x-icon name="clock" class="h-5 w-5 text-gray-500" /></div>
                <div class="mt-6 text-3xl font-extrabold tracking-tight">
                    <span x-text="stats.avgCookMinutes === null ? '–' : String(stats.avgCookMinutes).replace('.', ',')"></span>
                    <span class="text-base font-medium text-gray-600">Menit</span>
                </div>
            </div>
        </div>

        {{-- Papan dapur --}}
        <div class="mt-10 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight">Pesanan Dapur</h2>
                <p class="text-sm text-gray-600">Kelola alur pemrosesan pesanan secara langsung</p>
            </div>
            <div class="flex rounded-xl border border-blue-100 bg-blue-50/60 p-1 text-sm font-semibold">
                @foreach ($types as [$key, $label])
                    <button type="button" @click="type = '{{ $key }}'" class="rounded-lg px-4 py-1.5"
                        :class="type === '{{ $key }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach ($columns as [$key, $title, $hint])
                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3">
                        <h3 class="font-bold">{{ $title }}</h3>
                        <span class="text-[11px] font-bold text-gray-700">{{ $hint }}</span>
                    </div>

                    <p x-show="column('{{ $key }}').length === 0" class="py-8 text-center text-sm text-gray-400">Kosong</p>

                    <div class="space-y-3">
                        <template x-for="order in column('{{ $key }}')" :key="order.code">
                            <article class="rounded-xl border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span x-show="order.table" class="rounded bg-blue-100 px-1.5 py-0.5 text-[11px] font-bold" x-text="'Meja ' + order.table"></span>
                                            <span x-show="!order.table" class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold" x-text="order.orderType"></span>
                                            <span class="truncate font-bold" x-text="order.customer"></span>
                                        </div>
                                        <div class="mt-1 text-[11px] font-bold text-gray-600" x-text="'#' + order.code + ' • ' + order.paidAtLabel + ' WIB'"></div>
                                    </div>
                                    <div class="shrink-0 text-lg font-extrabold text-brand-600" x-text="rupiah(order.total)"></div>
                                </div>
                                <ul class="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-sm">
                                    <template x-for="item in order.items" :key="item.id">
                                        <li>
                                            <div class="flex justify-between gap-3" :class="item.unavailable ? 'text-gray-400 line-through' : ''">
                                                <span x-text="item.qty + 'x ' + item.name + (item.options.length ? ' (' + item.options.join(', ') + ')' : '')"></span>
                                                <span class="shrink-0 text-gray-600" x-text="rupiah(item.lineTotal)"></span>
                                            </div>
                                            <div x-show="item.note" class="mt-1 rounded-lg border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs">
                                                <b>Catatan:</b> <span x-text="item.note"></span>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </article>
                        </template>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-admin-layout>
