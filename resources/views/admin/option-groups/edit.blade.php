@php
    $input = 'rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<x-admin-layout :title="'Kelola Grup: '.$group->name">
    <form method="POST" action="{{ route('admin.option-groups.update', $group) }}" class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @method('PUT')
        @include('admin.option-groups._form')
    </form>

    <h3 class="mt-10 mb-3 font-semibold text-gray-800">Opsi di grup ini</h3>

    @if (old('_section') === 'options' && $errors->any())
        <div class="mb-3 rounded bg-red-100 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Harga tambahan (Rp)</th>
                    <th class="px-4 py-3">Urutan</th>
                    <th class="px-4 py-3">Tersedia</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group->options as $option)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-2"><input form="opt-{{ $option->id }}" name="name" value="{{ $option->name }}" required class="{{ $input }} w-40"></td>
                        <td class="px-4 py-2"><input form="opt-{{ $option->id }}" name="extra_price" type="number" min="0" value="{{ $option->extra_price }}" class="{{ $input }} w-28"></td>
                        <td class="px-4 py-2"><input form="opt-{{ $option->id }}" name="sort" type="number" min="0" value="{{ $option->sort }}" class="{{ $input }} w-20"></td>
                        <td class="px-4 py-2">
                            <input form="opt-{{ $option->id }}" type="hidden" name="is_available" value="0">
                            <input form="opt-{{ $option->id }}" type="checkbox" name="is_available" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked($option->is_available)>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <input form="opt-{{ $option->id }}" type="hidden" name="_section" value="options">
                            <button form="opt-{{ $option->id }}" class="font-semibold text-brand-700 hover:underline">Simpan</button>
                            <button form="del-{{ $option->id }}" class="ml-3 font-semibold text-red-600 hover:underline">Hapus</button>
                        </td>
                    </tr>
                @endforeach

                @if ($group->options->isEmpty())
                    <tr class="border-t border-gray-100 hover:bg-gray-50"><td colspan="5" class="px-4 py-4 text-center text-gray-500">Belum ada opsi.</td></tr>
                @endif

                <tr class="border-t bg-gray-50">
                    <td class="px-4 py-2"><input form="opt-new" name="name" placeholder="Nama opsi baru" required class="{{ $input }} w-40"></td>
                    <td class="px-4 py-2"><input form="opt-new" name="extra_price" type="number" min="0" value="0" class="{{ $input }} w-28"></td>
                    <td class="px-4 py-2"><input form="opt-new" name="sort" type="number" min="0" value="{{ $group->options->count() }}" class="{{ $input }} w-20"></td>
                    <td class="px-4 py-2">
                        <input form="opt-new" type="hidden" name="is_available" value="0">
                        <input form="opt-new" type="checkbox" name="is_available" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" checked>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <input form="opt-new" type="hidden" name="_section" value="options">
                        <button form="opt-new" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-brand-700">+ Tambah</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Form kosong ini dikaitkan lewat atribut form="..." di atas, karena <form> tidak boleh bersarang. --}}
    <form id="opt-new" method="POST" action="{{ route('admin.options.store', $group) }}">@csrf</form>
    @foreach ($group->options as $option)
        <form id="opt-{{ $option->id }}" method="POST" action="{{ route('admin.options.update', $option) }}">@csrf @method('PUT')</form>
        <form id="del-{{ $option->id }}" method="POST" action="{{ route('admin.options.destroy', $option) }}"
            onsubmit="return confirm('Pindahkan opsi ke Sampah?')">@csrf @method('DELETE')</form>
    @endforeach
</x-admin-layout>
