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

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <a href="{{ route('staff.orders.index') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border-2 border-brand-600 bg-brand-50 px-3 py-1.5 text-sm font-bold text-brand-700 hover:bg-brand-100">
                <x-icon name="receipt" class="h-4 w-4" /> <span class="hidden sm:inline">Papan</span> Karyawan
            </a>

            {{-- Menu akun: ganti nama, ganti kata sandi, dan Keluar di paling bawah --}}
            <div class="relative shrink-0" x-data="{
                menu: false,
                modal: @js($errors->hasBag('adminName') ? 'name' : ($errors->hasBag('adminPassword') ? 'password' : null)),
                show(which) { this.menu = false; this.modal = which; this.$nextTick(() => this.$refs[which + 'Field']?.focus()); },
            }" @keydown.escape.window="menu = false; modal = null" @click.outside="menu = false">
                <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu"
                    class="flex items-center gap-3 rounded-xl px-2 py-1.5 text-left hover:bg-gray-50">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-sm font-bold text-slate-800">{{ $initials }}</span>
                    <span class="hidden leading-tight sm:block">
                        <span class="block text-sm font-bold">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] text-gray-500">Admin</span>
                    </span>
                    <svg class="h-4 w-4 text-gray-500 transition" :class="menu ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
                </button>

                <div x-show="menu" x-cloak role="menu" class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <div class="truncate text-sm font-bold">{{ auth()->user()->name }}</div>
                        <div class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</div>
                    </div>
                    <div class="p-1.5 text-sm font-semibold">
                        <button type="button" role="menuitem" @click="show('name')" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left hover:bg-gray-50">
                            <x-icon name="pencil" class="h-4 w-4 text-gray-500" /> Ganti Nama
                        </button>
                        <button type="button" role="menuitem" @click="show('password')" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left hover:bg-gray-50">
                            <x-icon name="lock" class="h-4 w-4 text-gray-500" /> Ganti Kata Sandi
                        </button>
                    </div>
                    <form method="POST" action="{{ route('portal.logout') }}" class="border-t border-gray-100 p-1.5">
                        @csrf
                        <button role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-bold text-brand-700 hover:bg-brand-50">
                            <x-icon name="logout" class="h-4 w-4" /> Keluar
                        </button>
                    </form>
                </div>

                {{-- Modal ganti nama --}}
                <div x-show="modal === 'name'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="modal = null">
                    <form method="POST" action="{{ route('admin.account.name') }}" role="dialog" aria-modal="true" aria-labelledby="acc-name-title" class="w-full max-w-md rounded-2xl bg-white p-6 text-left shadow-xl">
                        @csrf
                        @method('PATCH')
                        <h2 id="acc-name-title" class="text-lg font-extrabold tracking-tight">Ganti Nama</h2>
                        <label class="mt-4 block text-sm font-semibold">Nama
                            <input type="text" name="name" x-ref="nameField" value="{{ old('name', auth()->user()->name) }}" required maxlength="255" autocomplete="name"
                                class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </label>
                        @error('name', 'adminName') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="modal = null" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Simpan</button>
                        </div>
                    </form>
                </div>

                {{-- Modal ganti kata sandi (kata sandi baru + konfirmasinya) --}}
                <div x-show="modal === 'password'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="modal = null">
                    <form method="POST" action="{{ route('admin.account.password') }}" role="dialog" aria-modal="true" aria-labelledby="acc-pw-title" class="w-full max-w-md rounded-2xl bg-white p-6 text-left shadow-xl">
                        @csrf
                        @method('PUT')
                        <h2 id="acc-pw-title" class="text-lg font-extrabold tracking-tight">Ganti Kata Sandi</h2>
                        <p class="mt-1 text-xs text-gray-600">Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.</p>

                        @if (auth()->user()->hasPassword())
                            <label class="mt-4 block text-sm font-semibold">Kata sandi saat ini
                                <input type="password" name="current_password" x-ref="passwordField" required autocomplete="current-password"
                                    class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </label>
                            @error('current_password', 'adminPassword') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                        @endif

                        <label class="mt-4 block text-sm font-semibold">Kata sandi baru
                            <input type="password" name="password" required autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </label>
                        @error('password', 'adminPassword') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                        <label class="mt-4 block text-sm font-semibold">Ulangi kata sandi baru
                            <input type="password" name="password_confirmation" required autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </label>

                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="modal = null" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
            </div>
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

    {{-- Kabar hasil aksi menu akun. Form-nya sendiri tetap terbuka di bawah modal gagal supaya kesalahannya bisa diperbaiki. --}}
    @if (session('name_changed'))
        <x-feedback-modal title="Nama berhasil diganti" message="Namamu di akun admin sudah diperbarui." />
    @endif
    @if (session('password_changed'))
        <x-feedback-modal title="Kata sandi berhasil diganti" message="Mulai sekarang masuk dengan kata sandi yang baru." />
    @endif
    @if ($feedback = session('feedback'))
        <x-feedback-modal :type="$feedback['type']" :title="$feedback['title']" :message="$feedback['message'] ?? null" />
    @endif
    @if ($errors->hasBag('adminName'))
        <x-feedback-modal type="error" title="Nama belum bisa diganti" :message="$errors->getBag('adminName')->first()" />
    @elseif ($errors->hasBag('adminPassword'))
        <x-feedback-modal type="error" title="Kata sandi belum bisa diganti" :message="$errors->getBag('adminPassword')->first()" />
    @endif

    <x-confirm-dialog />
</body>
</html>
