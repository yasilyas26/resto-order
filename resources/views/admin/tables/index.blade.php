<x-admin-layout title="Meja">
    <div class="mb-4">
        <a href="{{ route('admin.tables.create') }}"
            class="inline-block rounded bg-gray-800 px-4 py-2 text-sm text-white">+ Tambah meja</a>
    </div>

    <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-3">Nomor</th>
                    <th class="px-4 py-3">Kode (di stiker)</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-medium">{{ $table->number }}</td>
                        <td class="px-4 py-3 font-mono">{{ $table->code }}</td>
                        <td class="px-4 py-3">
                            @if ($table->is_active)
                                <span class="rounded bg-green-100 px-2 py-0.5 text-green-800">Aktif</span>
                            @else
                                <span class="rounded bg-gray-200 px-2 py-0.5 text-gray-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.tables.qr', $table) }}" class="text-blue-600 underline">QR</a>
                            <a href="{{ route('admin.tables.edit', $table) }}" class="ml-3 text-blue-600 underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.tables.regenerate', $table) }}" class="inline"
                                onsubmit="return confirm('Ganti kode meja? QR dan stiker lama langsung tidak berlaku.')">
                                @csrf
                                <button class="ml-3 text-amber-700 underline">Kode baru</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tables.destroy', $table) }}" class="inline"
                                onsubmit="return confirm('Pindahkan ke Sampah? QR meja ini langsung tidak berlaku.')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 text-red-600 underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Belum ada meja.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
