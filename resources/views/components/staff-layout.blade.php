@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title.' · Karyawan · ' : 'Karyawan · ' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/staff.js'])
</head>
<body class="min-h-screen bg-gray-50 font-inter text-gray-900 antialiased">
    @php
        $tabs = [
            ['Pesan Aktif', 'staff.orders.index', 'receipt'],
            ['Riwayat & Refund', 'staff.history.index', 'history'],
        ];
    @endphp

    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
        <div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-4 px-4 sm:px-6">
            <span class="shrink-0 whitespace-nowrap text-xl font-extrabold tracking-tight text-brand-700 sm:text-2xl">{{ config('app.name') }}</span>

            {{-- Tab di tengah (layar lebar) --}}
            <nav class="hidden items-center gap-6 md:flex">
                @foreach ($tabs as [$label, $route, $icon])
                    <a href="{{ route($route) }}"
                        class="flex items-center gap-2 border-b-2 px-2 py-5 text-sm font-semibold {{ request()->routeIs($route) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                        <x-icon :name="$icon" class="h-4 w-4" />
                        <span class="whitespace-nowrap">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('portal.logout') }}" class="flex shrink-0 items-center gap-2 sm:gap-3">
                @csrf
                {{-- Hanya admin yang melihat tombol ini; karyawan biasa tidak pernah. Middleware tetap yang mengamankan /admin. --}}
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border-2 border-brand-600 bg-brand-50 px-3 py-1.5 text-sm font-bold text-brand-700 hover:bg-brand-100">
                        <x-icon name="shield" class="h-4 w-4" /> <span class="hidden sm:inline">Dashboard</span> Admin
                    </a>
                @endif
                <span class="hidden text-sm text-gray-500 lg:inline">{{ auth()->user()->name }}</span>
                <button class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-bold text-brand-700 hover:bg-brand-50">
                    <x-icon name="logout" class="h-4 w-4" /> Keluar
                </button>
            </form>
        </div>

        {{-- Tab di baris kedua (HP), supaya tidak terpotong --}}
        <nav class="flex justify-center gap-6 border-t border-gray-100 md:hidden">
            @foreach ($tabs as [$label, $route, $icon])
                <a href="{{ route($route) }}"
                    class="flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-semibold {{ request()->routeIs($route) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500' }}">
                    <x-icon :name="$icon" class="h-4 w-4" />
                    <span class="whitespace-nowrap">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </header>

    {{ $slot }}
</body>
</html>
