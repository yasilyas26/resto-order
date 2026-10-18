<x-visitor-layout title="Daftar" :header="false">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl rounded-3xl border border-brand-100 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon name="flame" class="h-12 w-12" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Buat Akun Pelanggan</h1>
            <p class="mx-auto mt-1.5 max-w-sm text-center text-sm text-gray-500">Bergabung sekarang untuk menyimpan riwayat pesananmu di semua perangkat.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-7 space-y-5">
                @csrf

                <x-visitor.input name="name" label="Nama Lengkap" icon="user" placeholder="Contoh: Budi Santoso"
                    :value="old('name')" autocomplete="name" autofocus required />

                <x-visitor.input name="email" type="email" label="Alamat Email Aktif" icon="mail" placeholder="nama@email.com"
                    :value="old('email')" autocomplete="username" required />

                <div x-data="passwordField()">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-visitor.input name="password" type="password" label="Kata Sandi" icon="lock" placeholder="Buat kata sandi"
                            autocomplete="new-password" x-model="value" required />

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
                    <x-icon name="user" class="h-4 w-4" /> Daftar Akun Baru
                </button>
            </form>

            <div class="my-6 flex items-center gap-4 text-xs text-gray-500">
                <span class="h-px flex-1 bg-gray-200"></span> atau masuk dengan <span class="h-px flex-1 bg-gray-200"></span>
            </div>

            <div class="space-y-3">
                <x-visitor.google-button />
                <a href="{{ route('home') }}" class="block rounded-xl border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-600 hover:bg-gray-50">
                    Lanjut Memesan Sebagai Tamu
                </a>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">Masuk di Sini</a>
            </div>
        </div>
    </main>
</x-visitor-layout>
