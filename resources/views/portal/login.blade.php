<x-visitor-layout title="Portal Restoran" :header="false">
    <main class="flex min-h-screen items-center justify-center bg-white px-4 py-10">
        <div class="w-full max-w-md rounded-3xl border border-gray-200 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(15,23,42,0.18)] sm:p-9"
            x-data="{ tab: @js($tab) }">

            <div class="mx-auto flex h-28 w-28 items-center justify-center rounded-3xl border border-brand-200 bg-brand-100 text-brand-700">
                <x-icon name="flame" class="h-14 w-14" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Masuk Portal Restoran</h1>
            <p class="mt-1.5 text-center text-sm text-gray-500">Khusus Manajer, Kasir, Dapur, dan Tim Pramusaji.</p>

            {{-- Pilihan peran. Akun yang tidak cocok dengan tab yang dipilih ditolak di server. --}}
            <div class="mt-6 grid grid-cols-2 gap-1 rounded-xl border border-indigo-100 bg-indigo-50/60 p-1" role="tablist">
                <button type="button" role="tab" :aria-selected="tab === 'staff'" @click="tab = 'staff'"
                    class="flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition"
                    :class="tab === 'staff' ? 'bg-white text-brand-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'">
                    <x-icon name="id-card" class="h-4 w-4" /> Karyawan
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'admin'" @click="tab = 'admin'"
                    class="flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition"
                    :class="tab === 'admin' ? 'bg-white text-brand-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'">
                    <x-icon name="shield" class="h-4 w-4" /> Admin
                </button>
            </div>

            <form method="POST" action="{{ route('portal.login') }}" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" name="tab" :value="tab">

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-bold text-gray-900" x-text="tab === 'admin' ? 'Email Admin' : 'Email Karyawan'">Email Karyawan</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-500"><x-icon name="user" class="h-5 w-5" /></span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            :placeholder="tab === 'admin' ? 'Masukan Email anda' : 'Masukan Email anda'"
                            class="block w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <x-visitor.input name="password" type="password" label="Kata Sandi" icon="lock" placeholder="••••••••"
                    autocomplete="current-password" required />

                <label class="flex items-center gap-2.5 text-sm text-gray-700">
                    <input type="checkbox" name="remember" value="1" checked class="rounded border-gray-300 text-brand-600 focus:ring-brand-300">
                    Ingat sesi di perangkat terminal ini
                </label>

                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">
                    Masuk ke Sistem Operasional <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center">
                <p class="text-xs font-semibold text-brand-700">Lupa Kata Sandi? Hubungi Supervisor</p>
                <a href="{{ route('home') }}"
                    class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-100">
                    <x-icon name="utensils" class="h-4 w-4" /> Menuju Pemesanan Pelanggan (Mode Meja)
                </a>
            </div>
        </div>
    </main>
</x-visitor-layout>
