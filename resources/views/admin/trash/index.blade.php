<x-admin-layout title="Sampah">
    <p class="mb-6 text-sm text-gray-600">
        Data yang dihapus masuk ke sini dulu. "Hapus permanen" hanya aktif kalau data tidak lagi dirujuk data lain;
        kalau tidak aktif, alasannya ditulis di bawah namanya.
    </p>

    @foreach ($sections as $section)
        <h3 class="mt-8 mb-2 font-semibold text-gray-800">{{ $section['title'] }}</h3>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <tbody>
                    @forelse ($section['items'] as $item)
                        @php
                            $restoreBlock = $item->restoreBlocker();
                            $forceBlock = $item->forceDeleteBlocker();
                        @endphp
                        <tr class="border-t first:border-t-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $item->trashLabel() }}</div>
                                <div class="text-xs text-gray-500">dihapus {{ $item->deleted_at->diffForHumans() }}</div>
                                @if ($restoreBlock)
                                    <div class="mt-1 text-xs text-amber-700">Pulihkan: {{ $restoreBlock }}</div>
                                @endif
                                @if ($forceBlock)
                                    <div class="mt-1 text-xs text-amber-700">Hapus permanen: {{ $forceBlock }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.trash.restore', [$section['slug'], $item->id]) }}" class="inline">
                                    @csrf
                                    <button @disabled($restoreBlock) class="font-semibold text-brand-700 hover:underline disabled:cursor-not-allowed disabled:opacity-40">Pulihkan</button>
                                </form>
                                <form method="POST" action="{{ route('admin.trash.force', [$section['slug'], $item->id]) }}" class="inline"
                                    onsubmit="return confirm('Hapus permanen? Ini tidak bisa dibatalkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button @disabled($forceBlock) class="ml-3 font-semibold text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-40">Hapus permanen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-4 text-center text-gray-500">Kosong.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</x-admin-layout>
