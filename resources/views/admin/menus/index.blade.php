@php
    $tabs = [['menu', 'Menu'], ['kategori', 'Kategori'], ['opsi', 'Grup Opsi']];
    $th = 'px-4 py-3 text-xs font-bold uppercase tracking-wide text-gray-600';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700';
@endphp

<x-admin-layout title="Manajemen Menu" subtitle="Kelola daftar hidangan, kategori, grup opsi (level pedas, topping), harga, dan ketersediaan stok.">
    <x-slot:actions>
        @if ($tab === 'menu')
            <a href="{{ route('admin.menus.create') }}" class="{{ $primary }}"><x-icon name="plus" class="h-4 w-4" /> Tambah Menu Baru</a>
        @elseif ($tab === 'kategori')
            <a href="{{ route('admin.categories.create') }}" class="{{ $primary }}"><x-icon name="plus" class="h-4 w-4" /> Tambah Kategori</a>
        @else
            <a href="{{ route('admin.option-groups.create') }}" class="{{ $primary }}"><x-icon name="plus" class="h-4 w-4" /> Tambah Grup Opsi</a>
        @endif
    </x-slot:actions>

    {{-- Sub-tab --}}
    <div class="mb-4 inline-flex rounded-xl border border-gray-200 bg-white p-1 text-sm font-semibold">
        @foreach ($tabs as [$key, $label])
            <a href="{{ route('admin.menus.index', ['tab' => $key]) }}"
                class="rounded-lg px-4 py-1.5 {{ $tab === $key ? 'bg-brand-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($tab === 'menu')
        {{-- Pencarian dan saringan --}}
        <form method="GET" action="{{ route('admin.menus.index') }}" class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
            <label class="relative min-w-[220px] flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama menu…"
                    class="w-full rounded-xl border-gray-200 bg-blue-50/50 py-2 pl-9 text-sm focus:border-brand-500 focus:ring-brand-500">
            </label>
            <select name="category" onchange="this.form.submit()" class="rounded-xl border-gray-200 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">Semua Kategori</option>
                @foreach ($categoryOptions as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()" class="rounded-xl border-gray-200 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">Semua Status</option>
                <option value="available" @selected($status === 'available')>Tersedia</option>
                <option value="sold_out" @selected($status === 'sold_out')>Habis</option>
            </select>
            <a href="{{ route('admin.menus.index') }}" class="px-3 text-sm font-semibold text-gray-600 hover:text-gray-900">Reset Filter</a>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-blue-50/60">
                    <tr>
                        <th class="{{ $th }}">Foto</th>
                        <th class="{{ $th }}">Nama Menu</th>
                        <th class="{{ $th }}">Kategori</th>
                        <th class="{{ $th }}">Harga Dasar</th>
                        <th class="{{ $th }}">Grup Opsi Terkait</th>
                        <th class="{{ $th }}">Status Stok</th>
                        <th class="{{ $th }} text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @if ($menu->image)
                                    <img src="{{ $menu->imageUrl() }}" alt="" class="h-12 w-12 rounded-xl object-cover">
                                @else
                                    <div class="h-12 w-12 rounded-xl bg-gray-200"></div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold {{ $menu->is_available ? '' : 'text-gray-500' }}">{{ $menu->name }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800">{{ $menu->category->name }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 font-bold">Rp {{ number_format($menu->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($menu->effectiveOptionGroups() as $group)
                                        <span class="rounded-full border border-gray-800 px-2 py-0.5 text-[11px] font-bold">{{ $group->name }}{{ $group->isRequired() ? ' (Wajib)' : '' }}</span>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.menus.availability', $menu) }}" class="flex items-center gap-2">
                                    @csrf
                                    <button type="submit" role="switch" aria-checked="{{ $menu->is_available ? 'true' : 'false' }}" aria-label="Ubah ketersediaan {{ $menu->name }}"
                                        class="relative h-6 w-11 shrink-0 rounded-full transition {{ $menu->is_available ? 'bg-brand-600' : 'bg-gray-300' }}">
                                        <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all {{ $menu->is_available ? 'left-[22px]' : 'left-0.5' }}"></span>
                                    </button>
                                    <span class="text-xs font-semibold {{ $menu->is_available ? 'text-brand-700' : 'text-gray-500' }}">{{ $menu->is_available ? 'Tersedia' : 'Habis' }}</span>
                                </form>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.menus.edit', $menu) }}" class="font-semibold text-brand-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" class="inline" data-confirm-tone="soft" data-confirm-title="Pindahkan ke Tempat Sampah?" data-confirm-name="{{ $menu->name }}" data-confirm="Data ini bisa dipulihkan dari Tempat Sampah selama 30 hari sebelum dihapus permanen." data-confirm-label="Pindahkan ke Sampah">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada menu yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
                <span>Menampilkan <b>{{ $menus->firstItem() ?? 0 }}-{{ $menus->lastItem() ?? 0 }}</b> dari <b>{{ $menus->total() }}</b> menu</span>
                {{ $menus->links() }}
            </div>
        </div>
    @elseif ($tab === 'kategori')
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-blue-50/60">
                    <tr>
                        <th class="{{ $th }}">Urutan</th>
                        <th class="{{ $th }}">Nama</th>
                        <th class="{{ $th }}">Jumlah Menu</th>
                        <th class="{{ $th }}">Grup Opsi Bawaan</th>
                        <th class="{{ $th }} text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $category->sort }}</td>
                            <td class="px-4 py-3 font-bold">{{ $category->name }}</td>
                            <td class="px-4 py-3">{{ $category->menus_count }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $category->optionGroups->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold text-brand-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline" data-confirm-tone="soft" data-confirm-title="Pindahkan ke Tempat Sampah?" data-confirm-name="{{ $category->name }}" data-confirm="Data ini bisa dipulihkan dari Tempat Sampah selama 30 hari sebelum dihapus permanen." data-confirm-label="Pindahkan ke Sampah">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada kategori.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-blue-50/60">
                    <tr>
                        <th class="{{ $th }}">Nama</th>
                        <th class="{{ $th }}">Aturan Pilih</th>
                        <th class="{{ $th }}">Jumlah Opsi</th>
                        <th class="{{ $th }} text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold">{{ $group->name }}</td>
                            <td class="px-4 py-3">{{ $group->isRequired() ? 'Wajib' : 'Opsional' }}, pilih {{ $group->min_select }}–{{ $group->max_select }}</td>
                            <td class="px-4 py-3">{{ $group->options_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.option-groups.edit', $group) }}" class="font-semibold text-brand-700 hover:underline">Kelola</a>
                                <form method="POST" action="{{ route('admin.option-groups.destroy', $group) }}" class="inline" data-confirm-tone="soft" data-confirm-title="Pindahkan ke Tempat Sampah?" data-confirm-name="{{ $group->name }}" data-confirm="Data ini bisa dipulihkan dari Tempat Sampah selama 30 hari sebelum dihapus permanen." data-confirm-label="Pindahkan ke Sampah">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Belum ada grup opsi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
