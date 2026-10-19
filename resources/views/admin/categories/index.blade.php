<x-admin-layout title="Kategori Menu">
    <div class="mb-4">
        <a href="{{ route('admin.categories.create') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700"><x-icon name="plus" class="h-4 w-4" /> Tambah kategori</a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Urutan</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Grup opsi bawaan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $category->sort }}</td>
                        <td class="px-4 py-3">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $category->optionGroups->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold text-brand-700 hover:underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                class="inline" onsubmit="return confirm('Pindahkan ke Sampah?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Belum ada kategori.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
