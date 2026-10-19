@php
    $th = 'px-4 py-3 text-xs font-bold uppercase tracking-wide text-gray-600';
    $retention = \App\Support\Trash::RETENTION_DAYS;
@endphp

<x-admin-layout title="Tempat Sampah" subtitle="Daftar menu, kategori, grup opsi, dan meja yang dihapus sementara. Data dapat dipulihkan atau dihapus permanen.">
    <div class="mb-4 flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm">
        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-brand-700" />
        <p><b class="text-brand-700">Perhatian:</b> Hapus permanen akan menghapus data dari basis data dan tidak dapat dipulihkan. "Hapus" hanya aktif kalau data tidak lagi dirujuk data lain; alasannya ditulis di bawah namanya.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm">
        <p>Data di tempat sampah disimpan selama <b>{{ $retention }} hari</b> sebelum otomatis dihapus permanen oleh sistem.</p>
        <form method="POST" action="{{ route('admin.trash.empty') }}" onsubmit="return confirm('Hapus permanen SEMUA data di sampah? Ini tidak bisa dibatalkan.')">
            @csrf
            <button @disabled($counts['semua'] === 0)
                class="inline-flex items-center gap-1.5 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-brand-700 hover:bg-red-200 disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="trash" class="h-4 w-4" /> Kosongkan Semua Sampah
            </button>
        </form>
    </div>

    {{-- Tab per jenis data --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.trash.index', ['tab' => $key]) }}"
                class="inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold {{ $tab === $key ? 'border-brand-700 bg-brand-700 text-white' : 'border-brand-200 bg-white text-brand-700 hover:bg-brand-50' }}">
                {{ $label }}
                <span class="rounded px-1.5 text-[11px] font-bold {{ $tab === $key ? 'bg-white/25 text-white' : 'bg-red-100 text-brand-700' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-blue-50/60">
                <tr>
                    <th class="{{ $th }}">Jenis Data</th>
                    <th class="{{ $th }}">Nama Data / Item</th>
                    <th class="{{ $th }}">Waktu Penghapusan</th>
                    <th class="{{ $th }}">Sisa Waktu Retensi</th>
                    <th class="{{ $th }} text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $row)
                    @php
                        $item = $row['item'];
                        $restoreBlock = $item->restoreBlocker();
                        $forceBlock = $item->forceDeleteBlocker();
                        $left = \App\Support\Trash::daysLeft($item);
                    @endphp
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3"><span class="rounded-md bg-blue-100 px-2 py-0.5 text-[11px] font-bold">{{ $row['label'] }}</span></td>
                        <td class="px-4 py-3">
                            <div class="font-bold">{{ $item->trashLabel() }}</div>
                            @if ($restoreBlock)
                                <div class="mt-1 text-xs text-amber-700">Pulihkan: {{ $restoreBlock }}</div>
                            @endif
                            @if ($forceBlock)
                                <div class="mt-1 text-xs text-amber-700">Hapus permanen: {{ $forceBlock }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $item->deleted_at->translatedFormat('d M Y, H:i') }} WIB</td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $left > 0 ? "{$left} hari tersisa" : 'Dihapus otomatis malam ini' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.trash.restore', [$row['slug'], $item->id]) }}" class="inline">
                                @csrf
                                <button @disabled($restoreBlock) class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-bold text-brand-700 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40">Pulihkan</button>
                            </form>
                            <form method="POST" action="{{ route('admin.trash.force', [$row['slug'], $item->id]) }}" class="inline"
                                onsubmit="return confirm('Hapus permanen? Ini tidak bisa dibatalkan.')">
                                @csrf
                                @method('DELETE')
                                <button @disabled($forceBlock) class="ml-1 rounded-lg border border-brand-300 px-3 py-1 text-xs font-bold text-brand-700 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Tempat sampah kosong.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
            <span>Menampilkan <b>{{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }}</b> dari <b>{{ $items->total() }}</b> data terhapus</span>
            {{ $items->links() }}
        </div>
    </div>
</x-admin-layout>
