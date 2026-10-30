@props(['title' => null, 'header' => true, 'bg' => 'bg-white'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/visitor.js'])
</head>
<body class="min-h-screen font-inter text-gray-900 antialiased {{ $bg }}">
    {{-- Footer selalu di dasar layar walau isi halaman pendek. Isinya dibungkus div biasa (bukan flex) supaya mx-auto di halaman tetap bekerja. --}}
    <div class="flex min-h-screen flex-col">
        @if ($header)
            @include('visitor.partials.header')
        @endif

        <div class="flex-1">{{ $slot }}</div>

        @include('visitor.partials.footer')
    </div>

    {{-- Pemberitahuan singkat (mis. "MieGacoy ditambahkan ke keranjang") --}}
    <div x-data x-cloak x-show="$store.cart.toast"
        x-transition.opacity
        class="pointer-events-none fixed inset-x-0 bottom-24 z-[60] flex justify-center px-4 lg:bottom-8">
        <div class="max-w-md rounded-xl bg-gray-900 px-4 py-3 text-sm font-medium text-white shadow-lg" x-text="$store.cart.toast" role="status"></div>
    </div>

    @stack('scripts')
</body>
</html>
