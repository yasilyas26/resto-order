<x-visitor-layout title="Verifikasi Email" :header="false">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg rounded-3xl border border-brand-100 bg-white p-7 shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon name="mail" class="h-12 w-12" />
            </div>

            <h1 class="mt-5 text-center text-2xl font-extrabold tracking-tight">Cek Email Kamu</h1>
            <p class="mt-1.5 text-center text-sm text-gray-500">
                Kami sudah mengirim kode 6 digit ke
                <span class="font-semibold text-gray-800">{{ auth()->user()->email }}</span>.
                Masukkan kodenya di bawah untuk mengaktifkan akunmu.
            </p>

            @if (session('status') == 'verification-code-sent')
                <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    Kode baru sudah dikirim. Cek kotak masuk Gmail kamu.
                </div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" class="mt-6">
                @csrf
                <label for="code" class="mb-1.5 block text-sm font-semibold text-gray-900">Kode verifikasi</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                    autocomplete="one-time-code" autofocus required placeholder="••••••" value="{{ old('code') }}"
                    class="block w-full rounded-xl border border-brand-200 bg-white py-3 text-center text-2xl font-bold tracking-[0.5em] placeholder-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                @error('code')
                    <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">
                    Verifikasi <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>

            <div class="mt-6 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-gray-600">
                Belum ada emailnya? Cek juga folder <span class="font-semibold">Spam</span> atau <span class="font-semibold">Promosi</span>.
                Kode berlaku {{ \App\Models\User::EMAIL_CODE_TTL_MINUTES }} menit.
            </div>

            <form method="POST" action="{{ route('verification.send') }}" class="mt-3">
                @csrf
                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-700 hover:bg-brand-50">
                    Kirim Ulang Kode
                </button>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                Salah memasukkan email?
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="font-bold text-brand-700 hover:underline">Keluar dan daftar ulang</button>
                </form>
            </div>
        </div>
    </main>
</x-visitor-layout>
