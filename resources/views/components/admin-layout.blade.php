@props(['title'])

@php
    $links = [
        ['Dashboard', 'admin.index', ['admin.index'], 'bowl'],
        ['Kategori', 'admin.categories.index', ['admin.categories.*'], 'utensils'],
        ['Menu', 'admin.menus.index', ['admin.menus.*'], 'receipt'],
        ['Grup Opsi', 'admin.option-groups.index', ['admin.option-groups.*', 'admin.options.*'], 'flame'],
        ['Meja', 'admin.tables.index', ['admin.tables.*'], 'qr'],
        ['Sampah', 'admin.trash.index', ['admin.trash.*'], 'trash'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title }} · Admin · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-inter text-gray-900 antialiased">
    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <span class="shrink-0 whitespace-nowrap text-xl font-extrabold tracking-tight text-brand-700 sm:text-2xl">{{ config('app.name') }}</span>

            {{-- Tab di tengah (layar lebar) --}}
            <nav class="hidden items-center gap-4 lg:flex">
                @foreach ($links as [$label, $route, $patterns, $icon])
                    <a href="{{ route($route) }}"
                        class="flex items-center gap-2 border-b-2 px-2 py-5 text-sm font-semibold {{ request()->routeIs(...$patterns) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                        <x-icon :name="$icon" class="h-4 w-4" />
                        <span class="whitespace-nowrap">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('portal.logout') }}" class="flex shrink-0 items-center gap-3">
                @csrf
                <span class="hidden text-sm text-gray-500 sm:inline">{{ auth()->user()->name }}</span>
                <button class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-bold text-brand-700 hover:bg-brand-50">
                    <x-icon name="logout" class="h-4 w-4" /> Keluar
                </button>
            </form>
        </div>

        {{-- Tab di baris kedua (HP/tablet), bisa digeser kalau tidak muat --}}
        <nav class="flex gap-4 overflow-x-auto border-t border-gray-100 px-4 sm:px-6 lg:hidden">
            @foreach ($links as [$label, $route, $patterns, $icon])
                <a href="{{ route($route) }}"
                    class="flex shrink-0 items-center gap-2 border-b-2 px-2 py-3 text-sm font-semibold {{ request()->routeIs(...$patterns) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500' }}">
                    <x-icon :name="$icon" class="h-4 w-4" />
                    <span class="whitespace-nowrap">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-gray-900">{{ $title }}</h1>

        @if (session('status'))
            <div class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('error') }}</span>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
