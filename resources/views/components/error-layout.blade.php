@props(['code', 'title', 'message', 'icon' => 'alert', 'reload' => false])

{{-- Layout halaman error. Sengaja berdiri sendiri: tidak memakai database, sesi, atau data pengguna,
     supaya halaman ini tetap tampil kalau penyebab errornya justru database atau sesi. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white font-inter text-gray-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg rounded-3xl border border-brand-100 bg-white p-7 text-center shadow-[0_10px_40px_-12px_rgba(217,56,30,0.15)] sm:p-10">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-brand-100 text-brand-700">
                <x-icon :name="$icon" class="h-12 w-12" />
            </div>

            <p class="mt-6 text-sm font-bold uppercase tracking-widest text-brand-600">Error {{ $code }}</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
            <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-gray-500">{{ $message }}</p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                @if ($reload)
                    <button type="button" onclick="window.location.reload()"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
                        <x-icon name="refresh" class="h-4 w-4" /> Muat ulang
                    </button>
                    <a href="{{ url('/') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Ke beranda
                    </a>
                @else
                    <a href="{{ url('/') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
                        Ke beranda <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign('{{ url('/') }}')"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Kembali
                    </button>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
