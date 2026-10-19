@csrf

@php
    $selectedGroups = old('option_groups', isset($menu) ? $menu->optionGroups->pluck('id')->all() : []);
    $field = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div>
    <x-input-label for="category_id" value="Kategori" />
    <select id="category_id" name="category_id" class="{{ $field }}" required>
        <option value="">— pilih kategori —</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $menu->category_id ?? '') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="name" value="Nama menu" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $menu->name ?? '')" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="description" value="Deskripsi (opsional)" />
    <textarea id="description" name="description" rows="3" class="{{ $field }}">{{ old('description', $menu->description ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="price" value="Harga (Rp, sudah termasuk pajak)" />
    <x-text-input id="price" name="price" type="number" min="0" step="500" class="mt-1 block w-full" :value="old('price', $menu->price ?? '')" required />
    <x-input-error :messages="$errors->get('price')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="image" value="Foto (JPG/PNG/WebP, maks 2 MB)" />
    @if (isset($menu) && $menu->image)
        <img src="{{ $menu->imageUrl() }}" alt="" class="mt-2 h-28 w-28 rounded object-cover">
        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"> Hapus foto ini
        </label>
    @endif
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
    <x-input-error :messages="$errors->get('image')" class="mt-2" />
</div>

<div class="mt-4">
    <input type="hidden" name="is_available" value="0">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_available" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('is_available', $menu->is_available ?? true))>
        Tersedia (kalau tidak dicentang, menu tampil abu-abu dan tidak bisa dipesan)
    </label>
</div>

@php
    $categoryGroups = $categories->mapWithKeys(fn ($c) => [$c->id => $c->optionGroups->pluck('name')->all()]);
@endphp

{{-- Hanya tampil kalau kategori yang dipilih punya grup bawaan; kalau tidak, tidak ada yang bisa dikecualikan. --}}
<div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4"
    x-data="{ groups: @js($categoryGroups), current: '' }"
    x-show="(groups[current] || []).length" x-cloak
    x-init="const el = document.getElementById('category_id'); current = el.value; el.addEventListener('change', () => current = el.value)">
    <div class="text-sm font-medium text-gray-700">Grup opsi bawaan kategori</div>
    <p class="mt-1 text-sm text-gray-500">
        Menu ini otomatis mendapat: <span class="font-medium text-gray-800" x-text="(groups[current] || []).join(', ')"></span>
    </p>
    <input type="hidden" name="skip_category_options" value="0">
    <label class="mt-3 flex items-center gap-2 text-sm">
        <input type="checkbox" name="skip_category_options" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            @checked(old('skip_category_options', $menu->skip_category_options ?? false))>
        Jangan pakai grup bawaan kategori untuk menu ini
    </label>
</div>

<div class="mt-6">
    <div class="text-sm font-medium text-gray-700">Grup opsi yang dipasang langsung</div>
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
    <a href="{{ route('admin.menus.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Batal</a>
</div>
