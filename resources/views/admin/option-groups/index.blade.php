<x-admin-layout title="Grup Opsi">
    <div class="mb-4">
        <a href="{{ route('admin.option-groups.create') }}"
            class="inline-block rounded bg-gray-800 px-4 py-2 text-sm text-white">+ Tambah grup</a>
    </div>

    <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Aturan pilih</th>
                    <th class="px-4 py-3">Jumlah opsi</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-medium">{{ $group->name }}</td>
                        <td class="px-4 py-3">{{ $group->isRequired() ? 'Wajib' : 'Opsional' }}, pilih {{ $group->min_select }}–{{ $group->max_select }}</td>
                        <td class="px-4 py-3">{{ $group->options_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.option-groups.edit', $group) }}" class="text-blue-600 underline">Kelola</a>
                            <form method="POST" action="{{ route('admin.option-groups.destroy', $group) }}"
                                class="inline" onsubmit="return confirm('Pindahkan grup ke Sampah?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 text-red-600 underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Belum ada grup opsi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
