@php
    $th = 'px-4 py-3 text-xs font-bold uppercase tracking-wide text-gray-600';
@endphp

<x-admin-layout title="Manajemen Meja" subtitle="Atur meja makan, buat ulang kode unik, dan cetak QR meja.">
    {{-- Tiga angka ringkas --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-sm text-gray-700">Total Meja Resto <x-icon name="qr" class="h-5 w-5 text-gray-500" /></div>
            <div class="mt-3 text-4xl font-extrabold tracking-tight">{{ $stats['total'] }} <span class="text-lg font-medium text-gray-600">Meja</span></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-700">Meja Siap Digunakan</div>
            <div class="mt-3 text-4xl font-extrabold tracking-tight">{{ $stats['active'] }} <span class="text-lg font-medium text-gray-600">Meja Aktif</span></div>
            <div class="mt-1 text-xs text-gray-500">{{ $stats['inactive'] }} meja nonaktif</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-sm text-gray-700">
                Status Okupansi Meja
                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-brand-700">{{ $stats['percent'] }}% Okupansi</span>
            </div>
            <div class="mt-3 text-4xl font-extrabold tracking-tight">{{ $stats['occupied'] }} <span class="text-lg font-medium text-gray-600">Meja Terisi</span></div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-brand-600" style="width: {{ $stats['percent'] }}%"></div>
            </div>
        </div>
    </div>

    {{-- Cari + tombol tambah --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        {{-- Cari dan urutkan dalam satu form: mengganti urutan tidak menghilangkan kata pencarian. --}}
        <form method="GET" action="{{ route('admin.tables.index') }}" class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:flex-1">
            <label class="relative w-full max-w-md flex-1 sm:min-w-[240px]">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor meja atau kode…"
                    class="w-full rounded-xl border-gray-200 bg-white py-2.5 pl-9 text-sm focus:border-brand-500 focus:ring-brand-500">
            </label>

            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                Urutkan
                <select name="sort" onchange="this.form.submit()" aria-label="Urutkan daftar meja"
                    class="rounded-xl border-gray-200 bg-white py-2.5 pl-3 pr-9 text-sm font-semibold focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <a href="{{ route('admin.tables.create') }}"
            class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700"><x-icon name="plus" class="h-4 w-4" /> Tambah Meja Baru</a>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm"
        x-data="{ picked: [], all: @js($tables->pluck('id')), get every() { return this.all.length > 0 && this.picked.length === this.all.length },
            toggleAll(on) { this.picked = on ? [...this.all] : [] } }">
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3">
            <h2 class="font-bold">Daftar Meja Resto</h2>
            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs text-gray-600">{{ $tables->count() }} dari {{ $tables->total() }} meja ditampilkan</span>

            {{-- Muncul setelah ada meja yang dicentang --}}
            <form method="POST" action="{{ route('admin.tables.qr.download-many') }}" x-show="picked.length" x-cloak class="ml-auto flex items-center gap-3">
                @csrf
                <template x-for="id in picked" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <span class="text-sm text-gray-600"><b x-text="picked.length"></b> meja dipilih</span>
                <button type="button" @click="picked = []" class="text-sm font-semibold text-gray-600 hover:underline">Batal</button>
                <button class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700">Unduh QR</button>
            </form>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-blue-50/60">
                <tr>
                    <th class="w-10 px-4 py-3"><input type="checkbox" aria-label="Pilih semua meja di halaman ini" :checked="every" @change="toggleAll($event.target.checked)" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"></th>
                    <th class="{{ $th }}">Nomor / Nama Meja</th>
                    <th class="{{ $th }}">Kode Unik</th>
                    <th class="{{ $th }}">Status</th>
                    <th class="{{ $th }}">Okupansi</th>
                    <th class="{{ $th }}">Aksi QR</th>
                    <th class="{{ $th }} text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-4"><input type="checkbox" value="{{ $table->id }}" x-model.number="picked" aria-label="Pilih Meja {{ $table->number }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"></td>
                        <td class="px-4 py-4 font-bold">Meja {{ $table->number }}</td>
                        <td class="px-4 py-4"><span class="rounded-lg bg-blue-50 px-2.5 py-1 tabular-nums text-xs font-bold text-brand-700">{{ $table->code }}</span></td>
                        <td class="px-4 py-4">
                            @if ($table->is_active)
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Aktif</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if ($table->is_active && in_array($table->id, $occupiedIds, true))
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Terisi</span>
                            @else
                                <span class="text-xs text-gray-400">Kosong</span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <a href="{{ route('admin.tables.qr', $table) }}" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Lihat QR</a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-right">
                            <a href="{{ route('admin.tables.edit', $table) }}" class="font-semibold text-brand-700 hover:underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.tables.regenerate', $table) }}" class="inline"
                                data-confirm-tone="soft" data-confirm-title="Ganti kode meja?" data-confirm-name="Meja {{ $table->number }}" data-confirm="QR dan stiker lama langsung tidak berlaku. Cetak ulang stikernya setelah ini." data-confirm-label="Ganti Kode">
                                @csrf
                                <button class="ml-3 font-semibold text-amber-700 hover:underline">Kode baru</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tables.destroy', $table) }}" class="inline"
                                data-confirm-tone="soft" data-confirm-title="Pindahkan ke Tempat Sampah?" data-confirm-name="Meja {{ $table->number }}" data-confirm="QR meja ini langsung tidak berlaku. Meja bisa dipulihkan dari Tempat Sampah selama 30 hari." data-confirm-label="Pindahkan ke Sampah">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada meja yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
            <span>Menampilkan {{ $tables->count() }} dari {{ $tables->total() }} data meja</span>
            {{ $tables->links() }}
        </div>
    </div>
</x-admin-layout>
