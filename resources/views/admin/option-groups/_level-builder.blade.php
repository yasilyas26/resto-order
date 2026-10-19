{{-- Level cepat: bikin deretan level (mis. 0 sampai 8) sekaligus, tanpa membuat opsi satu per satu. --}}
@php
    $levelState = [
        'enabled' => true,
        'group_name' => old('group_name', 'Level Pedas'),
        'prefix' => old('prefix', 'Level'),
        'from' => old('from', 0),
        'to' => old('to', 8),
        'required' => (bool) old('required', true),
        'surchargeFrom' => old('surcharge_from', ''),
        'surcharge' => old('surcharge', ''),
    ];
    $input = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<form method="POST" action="{{ route('admin.option-groups.levels') }}" x-data="levelBuilder(@js($levelState))" class="space-y-4">
    @csrf

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="group_name" value="Nama grup" />
            <input id="group_name" name="group_name" type="text" x-model="group_name" maxlength="100" class="{{ $input }}">
            <x-input-error :messages="$errors->get('group_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="prefix" value="Awalan nama level" />
            <input id="prefix" name="prefix" type="text" x-model="prefix" maxlength="50" class="{{ $input }}">
            <x-input-error :messages="$errors->get('prefix')" class="mt-1" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="from" value="Dari level" />
            <input id="from" name="from" type="number" min="0" max="100" x-model="from" class="{{ $input }}">
            <x-input-error :messages="$errors->get('from')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="to" value="Sampai level" />
            <input id="to" name="to" type="number" min="0" max="100" x-model="to" class="{{ $input }}">
            <x-input-error :messages="$errors->get('to')" class="mt-1" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="surcharge_from" value="Tambahan harga mulai level (opsional)" />
            <input id="surcharge_from" name="surcharge_from" type="number" min="0" max="100" x-model="surchargeFrom" placeholder="mis. 3" class="{{ $input }}">
            <x-input-error :messages="$errors->get('surcharge_from')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="surcharge" value="Sebesar (Rp)" />
            <input id="surcharge" name="surcharge" type="number" min="0" step="500" x-model="surcharge" placeholder="mis. 1000" class="{{ $input }}">
            <x-input-error :messages="$errors->get('surcharge')" class="mt-1" />
        </div>
    </div>

    <input type="hidden" name="required" value="0">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="required" value="1" x-model="required" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
        Pembeli wajib memilih satu level
    </label>

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
        Kalau grup dengan nama yang sama sudah ada, grup itu dipakai lagi: hanya level yang belum ada yang ditambahkan,
        dan pengaturan serta harga yang sudah ada tidak diubah.
    </p>

    <x-primary-button x-bind:disabled="!!problem">Buat level</x-primary-button>
</form>
