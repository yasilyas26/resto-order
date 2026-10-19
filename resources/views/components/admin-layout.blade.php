@props(['title', 'subtitle' => null])

@php
    $links = [
        ['Ringkasan & Papan Order', 'admin.index', ['admin.index']],
        ['Manajemen Menu', 'admin.menus.index', ['admin.menus.*', 'admin.categories.*', 'admin.option-groups.*', 'admin.options.*']],
        ['Manajemen Meja', 'admin.tables.index', ['admin.tables.*']],
        ['Riwayat Transaksi', 'admin.transactions.index', ['admin.transactions.*']],
        ['Tempat Sampah', 'admin.trash.index', ['admin.trash.*']],
    ];
    $initials = collect(explode(' ', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
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
<body class="min-h-screen bg-slate-50 font-inter text-gray-900 antialiased">
    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
        <div class="mx-auto flex h-16 max-w-[1400px] items-center justify-between gap-4 px-4 sm:px-6">
            <span class="shrink-0 whitespace-nowrap text-2xl font-extrabold tracking-tight text-brand-700">{{ config('app.name') }}</span>

            <form method="POST" action="{{ route('portal.logout') }}" class="flex shrink-0 items-center gap-3">
                @csrf
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-sm font-bold text-slate-800">{{ $initials }}</span>
                <span class="hidden leading-tight sm:block">
                    <span class="block text-sm font-bold">{{ auth()->user()->name }}</span>
                    <span class="block text-[11px] text-gray-500">Admin</span>
                </span>
                <button class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-bold text-brand-700 hover:bg-brand-50">
                    <x-icon name="logout" class="h-4 w-4" /> <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>

        {{-- Tab; bisa digeser kalau layar sempit --}}
        <nav class="mx-auto flex max-w-[1400px] gap-6 overflow-x-auto px-4 sm:px-6">
            @foreach ($links as [$label, $route, $patterns])
                <a href="{{ route($route) }}"
                    class="shrink-0 whitespace-nowrap border-b-2 py-3 text-sm font-semibold {{ request()->routeIs(...$patterns) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    <main class="mx-auto max-w-[1400px] px-4 py-8 sm:px-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>

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

    {{-- Modal konfirmasi untuk semua form ber-atribut data-confirm (lihat resources/js/admin/confirmDialog.js) --}}
    <div x-data="confirmDialog" @submit.document="intercept($event)" @keydown.escape.window="close()">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="close()">
            <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                        :class="hard ? 'bg-red-100 text-brand-700' : 'bg-amber-100 text-amber-700'">
                        <x-icon name="trash" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 id="confirm-title" class="text-lg font-extrabold tracking-tight" x-text="title"></h2>
                        <p x-show="name" class="mt-1 break-words text-sm font-bold" x-text="name"></p>
                        <p id="confirm-message" class="mt-2 text-sm text-gray-600" x-text="message"></p>
                        <p x-show="hard" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-brand-700">Tindakan ini tidak bisa dibatalkan.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-ref="cancel" @click="close()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" @click="accept()" x-text="label"
                        class="rounded-xl px-4 py-2 text-sm font-bold text-white"
                        :class="hard ? 'bg-brand-700 hover:bg-brand-800' : 'bg-amber-600 hover:bg-amber-700'"></button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
