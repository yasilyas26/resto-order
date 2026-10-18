<x-visitor-layout title="Selamat datang" :header="false">
    <main x-data="scanner" x-init="code = @js(old('code', ''))" class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl rounded-3xl border border-gray-200 bg-white p-6 shadow-[0_10px_40px_-10px_rgba(217,56,30,0.18)] sm:p-9">
            <h1 class="text-center text-3xl font-extrabold tracking-tight sm:text-4xl">Selamat Datang di {{ config('app.name') }}!</h1>
            <p class="mx-auto mt-3 max-w-md text-center text-gray-500">
                Scan QR di mejamu atau masukkan kode meja untuk mulai memesan langsung dari mejamu tanpa antre kasir.
            </p>

            @if (session('error'))
                <div class="mt-5 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ session('error') }}</div>
            @endif

            <button type="button" @click="start()"
                class="mt-7 flex w-full items-center justify-center gap-3 rounded-2xl bg-brand-600 px-5 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/30 hover:bg-brand-700">
                <x-icon name="qr" class="h-6 w-6" />
                Scan QR dengan Kamera
            </button>

            <div class="my-7 flex items-center gap-4 text-sm text-gray-500">
                <span class="h-px flex-1 bg-gray-200"></span>
                atau masukkan kode manual
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>

            <form method="POST" action="{{ route('table.enter') }}">
                @csrf

                <label for="code" class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <x-icon name="key" class="h-4 w-4 text-brand-600" />
                    Kode Meja / Table ID
                </label>

                <div class="relative">
                    <input id="code" name="code" type="text" x-model="code"
                        @input="code = code.toUpperCase().replace(/[^A-Z0-9-]/g, '')"
                        maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Contoh: A07-K3F9"
                        class="block w-full rounded-2xl border bg-white px-4 py-4 pr-12 text-lg font-bold uppercase tracking-widest placeholder:text-sm placeholder:font-normal placeholder:normal-case placeholder:tracking-normal focus:border-brand-500 focus:ring-2 focus:ring-brand-200 {{ $errors->has('code') ? 'border-red-400' : 'border-gray-200' }}">
                    <button type="button" x-show="code" x-cloak @click="code = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-700" aria-label="Hapus kode">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                @error('code')
                    <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border px-5 py-3.5 text-sm font-bold transition"
                    :class="code.length >= 3 ? 'border-brand-600 bg-brand-600 text-white hover:bg-brand-700' : 'border-indigo-100 bg-indigo-50 text-brand-700 hover:bg-indigo-100'">
                    Lanjut ke Menu
                    <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>
        </div>

        {{-- Jendela kamera pemindai QR --}}
        <div x-show="open" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/70 p-4"
            @keydown.escape.window="close()">
            <div class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold">Arahkan kamera ke QR di meja</h2>
                    <button type="button" @click="close()" class="rounded-full p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Tutup">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div id="qr-reader" class="overflow-hidden rounded-2xl bg-gray-100" x-show="!error || starting"></div>

                <p x-show="starting" class="mt-3 text-center text-sm text-gray-500">Membuka kamera…</p>
                <p x-show="error" x-text="error" class="mt-3 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert"></p>

                <button type="button" @click="close()" class="mt-4 w-full rounded-xl border border-gray-200 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Tutup, ketik kode saja
                </button>
            </div>
        </div>
    </main>
</x-visitor-layout>
