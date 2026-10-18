<x-visitor-layout title="Masuk" :header="false">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg rounded-3xl border border-brand-100 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon name="flame" class="h-12 w-12" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Masuk ke Akun {{ config('app.name') }}</h1>
            <p class="mt-1.5 text-center text-sm text-gray-500">Gunakan akun terdaftar untuk menyimpan riwayat pesananmu di semua perangkat.</p>

            @if (session('status'))
                <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
                @csrf

                <x-visitor.input name="email" type="email" label="Email" icon="mail" placeholder="nama@email.com"
                    :value="old('email')" autocomplete="username" autofocus required />

                <x-visitor.input name="password" type="password" label="Kata Sandi" icon="lock" placeholder="Masukkan kata sandi akun"
                    autocomplete="current-password" required>
                    <x-slot:aside>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-700 hover:underline">Lupa Password?</a>
                        @endif
                    </x-slot:aside>
                </x-visitor.input>

                <label class="flex items-center gap-2.5 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-brand-300 text-brand-600 focus:ring-brand-300">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">
                    Masuk Sekarang <x-icon name="arrow-right" class="h-4 w-4" />
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
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">Daftar Sekarang</a>
            </div>
        </div>
    </main>
</x-visitor-layout>
