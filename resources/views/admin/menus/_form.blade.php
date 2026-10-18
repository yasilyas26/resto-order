@csrf

@php
    $selectedGroups = old('option_groups', isset($menu) ? $menu->optionGroups->pluck('id')->all() : []);
    $field = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
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
            <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300"> Hapus foto ini
        </label>
    @endif
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
    <x-input-error :messages="$errors->get('image')" class="mt-2" />
</div>

<div class="mt-4">
    <input type="hidden" name="is_available" value="0">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_available" value="1" class="rounded border-gray-300" @checked(old('is_available', $menu->is_available ?? true))>
        Tersedia (kalau tidak dicentang, menu tampil abu-abu dan tidak bisa dipesan)
    </label>
</div>

@php
    $categoryGroups = $categories->mapWithKeys(fn ($c) => [$c->id => $c->optionGroups->pluck('name')->all()]);
@endphp

<div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4"
    x-data="{ groups: @js($categoryGroups), current: '' }"
    x-init="const el = document.getElementById('category_id'); current = el.value; el.addEventListener('change', () => current = el.value)">
    <div class="text-sm font-medium text-gray-700">Grup opsi bawaan kategori</div>
    <p class="mt-1 text-sm text-gray-500" x-show="(groups[current] || []).length" x-cloak>
        Menu ini otomatis mendapat: <span class="font-medium text-gray-800" x-text="(groups[current] || []).join(', ')"></span>
    </p>
    <p class="mt-1 text-sm text-gray-500" x-show="!(groups[current] || []).length">Kategori ini belum punya grup opsi bawaan. Atur di menu "Kategori".</p>
    <input type="hidden" name="skip_category_options" value="0">
    <label class="mt-3 flex items-center gap-2 text-sm">
        <input type="checkbox" name="skip_category_options" value="1" class="rounded border-gray-300"
            @checked(old('skip_category_options', $menu->skip_category_options ?? false))>
        Jangan pakai grup bawaan kategori untuk menu ini
    </label>
</div>

<div class="mt-6">
    <div class="text-sm font-medium text-gray-700">Grup opsi yang dipasang langsung</div>
    @forelse ($optionGroups as $group)
        <label class="mt-2 flex items-start gap-2 text-sm">
            <input type="checkbox" name="option_groups[]" value="{{ $group->id }}" class="mt-1 rounded border-gray-300"
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

{{-- Level cepat: bikin deretan level (mis. 0 sampai 8) sekaligus, tanpa membuat opsi satu per satu. --}}
@php
    $levelState = [
        'enabled' => (bool) old('level_enabled', false),
        'group_name' => old('level_group_name', 'Level Pedas'),
        'prefix' => old('level_prefix', 'Level'),
        'from' => old('level_from', 0),
        'to' => old('level_to', 8),
        'required' => (bool) old('level_required', true),
        'surchargeFrom' => old('level_surcharge_from', ''),
        'surcharge' => old('level_surcharge', ''),
    ];
@endphp

<div class="mt-8 rounded-xl border border-gray-200 bg-gray-50 p-4" x-data="levelBuilder(@js($levelState))">
    <input type="hidden" name="level_enabled" value="0">
    <label class="flex items-start gap-3">
        <input type="checkbox" name="level_enabled" value="1" x-model="enabled" class="mt-1 rounded border-gray-300">
        <span>
            <span class="block text-sm font-semibold text-gray-800">Tambahkan level (mis. level pedas 0 sampai 8)</span>
            <span class="block text-sm text-gray-500">Semua level dibuat sekaligus dan langsung dipasang ke menu ini. Tidak perlu membuat opsi satu per satu.</span>
        </span>
    </label>

    <div x-show="enabled" x-cloak class="mt-4 space-y-4 border-t border-gray-200 pt-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="level_group_name" value="Nama grup" />
                <input id="level_group_name" name="level_group_name" type="text" x-model="group_name" maxlength="100"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_group_name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="level_prefix" value="Awalan nama level" />
                <input id="level_prefix" name="level_prefix" type="text" x-model="prefix" maxlength="50"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_prefix')" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="level_from" value="Dari level" />
                <input id="level_from" name="level_from" type="number" min="0" max="100" x-model="from"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_from')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="level_to" value="Sampai level" />
                <input id="level_to" name="level_to" type="number" min="0" max="100" x-model="to"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_to')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="level_surcharge_from" value="Tambahan harga mulai level (opsional)" />
                <input id="level_surcharge_from" name="level_surcharge_from" type="number" min="0" max="100" x-model="surchargeFrom" placeholder="mis. 3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_surcharge_from')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="level_surcharge" value="Sebesar (Rp)" />
                <input id="level_surcharge" name="level_surcharge" type="number" min="0" step="500" x-model="surcharge" placeholder="mis. 1000"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <x-input-error :messages="$errors->get('level_surcharge')" class="mt-1" />
            </div>
        </div>

        <input type="hidden" name="level_required" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="level_required" value="1" x-model="required" class="rounded border-gray-300">
            Pembeli wajib memilih satu level
        </label>

        {{-- Pratinjau --}}
        <div>
            <div class="text-sm font-medium text-gray-700">Yang akan dibuat:</div>
            <p x-show="problem" x-text="problem" class="mt-1 text-sm text-red-600"></p>
            <ul class="mt-2 flex flex-wrap gap-2" x-show="!problem">
                <template x-for="level in levels" :key="level.name">
                    <li class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm">
                        <span class="font-medium" x-text="level.name"></span>
                        <span class="ml-1 text-xs text-gray-500" x-text="price(level.extra)"></span>
                    </li>
                </template>
            </ul>
        </div>

        <p class="text-xs text-gray-500">
            Kalau grup dengan nama yang sama sudah ada (mis. dibuat untuk menu lain), grup itu dipakai lagi: hanya level yang belum ada yang ditambahkan,
            dan pengaturan serta harga yang sudah ada tidak diubah.
        </p>
    </div>
</div>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>Simpan</x-primary-button>
    <a href="{{ route('admin.menus.index') }}" class="text-sm text-gray-600 underline">Batal</a>
</div>
