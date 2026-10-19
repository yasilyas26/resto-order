@csrf

<div>
    <x-input-label for="number" value="Nomor meja (huruf/angka, mis. A07)" />
    <x-text-input id="number" name="number" type="text" class="mt-1 block w-full"
        :value="old('number', $table->number ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('number')" class="mt-2" />
</div>

<div class="mt-4">
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            @checked(old('is_active', $table->is_active ?? true))>
        Aktif (meja nonaktif tidak bisa dipakai untuk order)
    </label>
</div>

@isset($table)
    <p class="mt-4 text-sm text-gray-500">
        Kode meja: <span class="font-mono font-semibold">{{ $table->code }}</span>.
        Kode tidak berubah saat nomor diganti; pakai tombol "Kode baru" di daftar meja kalau perlu diganti.
    </p>
@endisset

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>Simpan</x-primary-button>
    <a href="{{ route('admin.tables.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Batal</a>
</div>
