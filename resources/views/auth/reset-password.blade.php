<x-visitor-layout title="Atur Ulang Kata Sandi" :header="false">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl rounded-3xl border border-brand-100 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon name="lock" class="h-12 w-12" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Buat Kata Sandi Baru</h1>
            <p class="mx-auto mt-1.5 max-w-sm text-center text-sm text-gray-500">Pilih kata sandi baru untuk akunmu. Tautan ini berlaku 15 menit dan hanya bisa dipakai sekali.</p>

            <form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-visitor.input name="email" type="email" label="Email" icon="mail" placeholder="nama@email.com"
                    :value="old('email', $request->email)" autocomplete="username" required />

                <div x-data="passwordField()">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-visitor.input name="password" type="password" label="Kata Sandi Baru" icon="lock" placeholder="Buat kata sandi"
                            autocomplete="new-password" autofocus x-model="value" required />

                        <x-visitor.input name="password_confirmation" type="password" label="Konfirmasi Kata Sandi" icon="lock" placeholder="Ulangi kata sandi"
                            autocomplete="new-password" required />
                    </div>

                    <div class="mt-3 flex items-center justify-between text-[11px] font-semibold text-gray-500">
                        <span>Kekuatan Sandi:</span>
                        <span x-text="label">Belum terisi</span>
                    </div>
                    <div class="mt-1.5 grid grid-cols-3 gap-2">
                        <template x-for="index in [0, 1, 2]" :key="index">
                            <span class="h-1.5 rounded-full transition-colors" :class="barClass(index)"></span>
                        </template>
                    </div>
                    <p class="mt-2 flex items-center gap-1.5 text-[11px] text-gray-500">
                        <x-icon name="info" class="h-3.5 w-3.5" />
                        Minimal 8 karakter, kombinasi huruf besar, huruf kecil, dan angka.
                    </p>
                </div>

                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">
                    Simpan Kata Sandi Baru <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </main>
</x-visitor-layout>
