<x-admin-layout title="Menu">
    <div class="mb-4">
        <a href="{{ route('admin.menus.create') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700"><x-icon name="plus" class="h-4 w-4" /> Tambah menu</a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Foto</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Harga</th>
                    <th class="px-4 py-3">Opsi</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($menus as $menu)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @if ($menu->image)
                                <img src="{{ $menu->imageUrl() }}" alt="" class="h-12 w-12 rounded object-cover">
                            @else
                                <div class="h-12 w-12 rounded bg-gray-200"></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $menu->name }}</td>
                        <td class="px-4 py-3">{{ $menu->category->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">Rp {{ number_format($menu->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $menu->effectiveOptionGroups()->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($menu->is_available)
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Tersedia</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Habis</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.menus.edit', $menu) }}" class="font-semibold text-brand-700 hover:underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                                class="inline" onsubmit="return confirm('Pindahkan ke Sampah?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Belum ada menu.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
