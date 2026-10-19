@csrf

@php
    // Error milik form opsi (di bawah) tidak boleh muncul di form grup ini, karena nama field-nya sama.
    $showErrors = old('_section') !== 'options';
@endphp

<div>
    <x-input-label for="name" value="Nama grup (mis. Level pedas, Topping)" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $group->name ?? '')" required />
    @if ($showErrors)
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    @endif
</div>

<div class="mt-4 grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="min_select" value="Minimal dipilih (1 = wajib)" />
        <x-text-input id="min_select" name="min_select" type="number" min="0" class="mt-1 block w-full" :value="old('min_select', $group->min_select ?? 0)" />
        @if ($showErrors)
            <x-input-error :messages="$errors->get('min_select')" class="mt-2" />
        @endif
    </div>
    <div>
        <x-input-label for="max_select" value="Maksimal dipilih (1 = pilih satu)" />
        <x-text-input id="max_select" name="max_select" type="number" min="1" class="mt-1 block w-full" :value="old('max_select', $group->max_select ?? 1)" required />
        @if ($showErrors)
            <x-input-error :messages="$errors->get('max_select')" class="mt-2" />
        @endif
    </div>
</div>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>Simpan</x-primary-button>
    <a href="{{ route('admin.option-groups.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Kembali</a>
</div>
