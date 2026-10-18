@php
    $user = auth()->user();
    $initials = $user
        ? \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('')
        : null;
    $menuUrl = $currentTable ? route('menu') : route('home');
    $nav = [
        ['Menu Utama', $menuUrl, request()->routeIs('menu', 'cart', 'home')],
        ['Riwayat Pesanan', route('riwayat'), request()->routeIs('riwayat', 'order.show')],
    ];
@endphp

{{-- Header halaman pengunjung. Sengaja tanpa link ke /admin atau /staff (PRD G-13). --}}
<header class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ $menuUrl }}" class="shrink-0 whitespace-nowrap text-xl font-extrabold tracking-tight text-brand-700 sm:text-2xl">{{ config('app.name') }}</a>

            @if ($currentTable)
                <a href="{{ route('table.change') }}" title="Ganti meja"
                    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100">
                    <x-icon name="utensils" class="h-3.5 w-3.5" />
                    <span>Meja {{ $currentTable->number }}</span>
                    <span class="font-medium text-brand-500 underline">Ganti</span>
                </a>
            @endif
        </div>

        <nav class="hidden items-center gap-8 md:flex">
            @foreach ($nav as [$label, $url, $active])
                <a href="{{ $url }}"
                    class="border-b-2 py-5 text-sm font-semibold {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-900' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-3">
            @if ($currentTable)
                <a href="{{ route('cart') }}" class="relative rounded-full p-2 text-gray-600 hover:bg-gray-100" aria-label="Keranjang">
                    <x-icon name="bag" class="h-6 w-6" />
                    <span x-data x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count"
                        class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white"></span>
                </a>
            @endif

            @if ($user)
                <a href="{{ route('riwayat') }}" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-gray-100">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-semibold">{{ \Illuminate\Support\Str::limit($user->name, 16) }}</span>
                        <span class="block text-xs text-gray-500">{{ match ($user->role) {
                            \App\Enums\Role::Admin => 'Admin',
                            \App\Enums\Role::Staff => 'Karyawan',
                            default => 'Pelanggan',
                        } }}</span>
                    </span>
                </a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Masuk / Profil</a>
            @endif
        </div>
    </div>

    <nav class="flex justify-center gap-8 border-t border-gray-100 md:hidden">
        @foreach ($nav as [$label, $url, $active])
            <a href="{{ $url }}"
                class="border-b-2 py-3 text-sm font-semibold {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500' }}">{{ $label }}</a>
        @endforeach
    </nav>
</header>
