<x-admin-layout title="Meja">
    <div class="mb-4">
        <a href="{{ route('admin.tables.create') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700"><x-icon name="plus" class="h-4 w-4" /> Tambah meja</a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nomor</th>
                    <th class="px-4 py-3">Kode (di stiker)</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $table->number }}</td>
                        <td class="px-4 py-3 font-mono">{{ $table->code }}</td>
                        <td class="px-4 py-3">
                            @if ($table->is_active)
                                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Aktif</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.tables.qr', $table) }}" class="font-semibold text-brand-700 hover:underline">QR</a>
                            <a href="{{ route('admin.tables.edit', $table) }}" class="ml-3 font-semibold text-brand-700 hover:underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.tables.regenerate', $table) }}" class="inline"
                                onsubmit="return confirm('Ganti kode meja? QR dan stiker lama langsung tidak berlaku.')">
                                @csrf
                                <button class="ml-3 font-semibold text-amber-700 hover:underline">Kode baru</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tables.destroy', $table) }}" class="inline"
                                onsubmit="return confirm('Pindahkan ke Sampah? QR meja ini langsung tidak berlaku.')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
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
