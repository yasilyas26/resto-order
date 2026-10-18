<x-admin-layout title="Menu">
    <div class="mb-4">
        <a href="{{ route('admin.menus.create') }}"
            class="inline-block rounded bg-gray-800 px-4 py-2 text-sm text-white">+ Tambah menu</a>
    </div>

    <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
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
                    <tr class="border-t">
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
                        <td class="px-4 py-3 text-gray-500">{{ $menu->optionGroups->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($menu->is_available)
                                <span class="rounded bg-green-100 px-2 py-0.5 text-green-800">Tersedia</span>
                            @else
                                <span class="rounded bg-gray-200 px-2 py-0.5 text-gray-700">Habis</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.menus.edit', $menu) }}" class="text-blue-600 underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                                class="inline" onsubmit="return confirm('Pindahkan ke Sampah?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 text-red-600 underline">Hapus</button>
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
