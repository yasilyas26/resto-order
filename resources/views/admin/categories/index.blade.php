<x-admin-layout title="Kategori Menu">
    <div class="mb-4">
        <a href="{{ route('admin.categories.create') }}"
            class="inline-block rounded bg-gray-800 px-4 py-2 text-sm text-white">+ Tambah kategori</a>
    </div>

    <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-3">Urutan</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Grup opsi bawaan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $category->sort }}</td>
                        <td class="px-4 py-3">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $category->optionGroups->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-blue-600 underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                class="inline" onsubmit="return confirm('Pindahkan ke Sampah?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 text-red-600 underline">Hapus</button>
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
