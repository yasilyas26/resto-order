<x-visitor-layout title="Selamat datang" :header="false">
    <main x-data="scanner" class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl rounded-3xl border border-gray-200 bg-white p-6 shadow-[0_10px_40px_-10px_rgba(217,56,30,0.18)] sm:p-9">
            <h1 class="text-center text-3xl font-extrabold tracking-tight sm:text-4xl">Selamat Datang di {{ config('app.name') }}!</h1>
            <p class="mx-auto mt-3 max-w-md text-center text-gray-500">
                Scan QR di mejamu untuk mulai memesan langsung dari tempat duduk, tanpa antre di kasir.
            </p>

            @if (session('error'))
                <div class="mt-5 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ session('error') }}</div>
            @endif

            <button type="button" @click="start()"
                class="mt-7 flex w-full items-center justify-center gap-3 rounded-2xl bg-brand-600 px-5 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/30 hover:bg-brand-700">
                <x-icon name="qr" class="h-6 w-6" />
                Scan QR dengan Kamera
            </button>

            @error('code')
                <p class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>
            @enderror

            <p class="mt-5 text-center text-sm text-gray-500">
                Bisa juga scan memakai kamera bawaan HP. QR-nya ada di meja.
            </p>
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
                    Tutup
                </button>
            </div>
        </div>
    </main>
</x-visitor-layout>
