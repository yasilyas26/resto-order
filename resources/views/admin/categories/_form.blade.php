@csrf

@php
    $selectedGroups = old('option_groups', isset($category) ? $category->optionGroups->pluck('id')->all() : []);
@endphp

<div>
    <x-input-label for="name" value="Nama kategori" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
        :value="old('name', $category->name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="sort" value="Urutan (angka kecil tampil lebih dulu)" />
    <x-text-input id="sort" name="sort" type="number" min="0" class="mt-1 block w-full"
        :value="old('sort', $category->sort ?? 0)" />
    <x-input-error :messages="$errors->get('sort')" class="mt-2" />
</div>

<div class="mt-6">
    <div class="text-sm font-medium text-gray-700">Grup opsi bawaan kategori</div>
    <p class="mt-1 text-sm text-gray-500">Otomatis dipakai semua menu di kategori ini (mis. Level pedas untuk kategori Mie). Menu tertentu bisa mematikannya lewat form menu.</p>
    @forelse ($optionGroups as $group)
        <label class="mt-2 flex items-start gap-2 text-sm">
            <input type="checkbox" name="option_groups[]" value="{{ $group->id }}" class="mt-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                @checked(in_array($group->id, $selectedGroups))>
            <span>
                <span class="font-medium">{{ $group->name }}</span>
                <span class="text-gray-500">({{ $group->isRequired() ? 'wajib' : 'opsional' }}, pilih {{ $group->min_select }}–{{ $group->max_select }})</span>
                <span class="block text-gray-500">{{ $group->options->pluck('name')->join(', ') ?: 'belum ada opsi' }}</span>
            </span>
        </label>
    @empty
        <p class="mt-1 text-sm text-gray-500">Belum ada grup opsi. Buat dulu di menu "Grup Opsi".</p>
    @endforelse
    <x-input-error :messages="$errors->get('option_groups.*')" class="mt-2" />
</div>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>Simpan</x-primary-button>
    <a href="{{ route('admin.categories.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Batal</a>
</div>
