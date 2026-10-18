@props(['name', 'label' => null, 'icon' => null, 'type' => 'text', 'required' => false, 'hint' => null, 'aside' => null])

{{-- Kolom isian bergaya halaman pelanggan: label, ikon di kiri, galat validasi di bawah. --}}
<div {{ $attributes->only('class') }}>
    @if ($label)
        <div class="mb-1.5 flex items-center justify-between">
            <label for="{{ $name }}" class="text-sm font-semibold text-gray-900">
                {{ $label }} @if ($required)<span class="text-brand-600">*</span>@endif
            </label>
            {{ $aside }}
        </div>
    @endif

    <div class="relative" @if ($type === 'password') x-data="{ show: false }" @endif>
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        <input id="{{ $name }}" name="{{ $name }}"
            @if ($type === 'password') :type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'block w-full rounded-xl border border-brand-200 bg-white py-3 text-sm placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 '.($icon ? 'pl-11 ' : 'pl-4 ').($type === 'password' ? 'pr-11' : 'pr-4')]) }}
            @if ($required) required @endif>

        @if ($type === 'password')
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-500 hover:text-gray-800"
                :aria-label="show ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'">
                <x-icon name="eye" class="h-5 w-5" x-show="!show" />
                <x-icon name="eye-off" class="h-5 w-5" x-show="show" x-cloak />
            </button>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
