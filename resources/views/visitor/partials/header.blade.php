@php
    $user = auth()->user();
    $initials = $user
        ? \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('')
        : null;
    $menuUrl = $currentTable ? route('menu') : route('home');
    // Link Riwayat tampil untuk semua; tamu yang mengkliknya diarahkan ke login oleh middleware auth.
    $nav = [
        ['Menu Utama', $menuUrl, request()->routeIs('menu', 'cart', 'home')],
        ['Riwayat Pesanan', route('riwayat'), request()->routeIs('riwayat', 'order.show')],
    ];
@endphp

{{-- Header halaman pengunjung. Sengaja tanpa link ke /admin atau /staff (PRD G-13). --}}
<header class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-3 sm:gap-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-1 sm:gap-3">
            <button x-data type="button" @click="$dispatch('open-nav')" aria-label="Buka menu"
                class="-ml-1 shrink-0 rounded-full p-2 text-gray-700 hover:bg-gray-100 md:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ $menuUrl }}" class="shrink-0 whitespace-nowrap text-[17px] font-extrabold tracking-tight text-brand-700 sm:text-2xl">{{ config('app.name') }}</a>

            @if ($currentTable)
                <a href="{{ route('table.change') }}" title="Ganti meja"
                    class="ml-0.5 inline-flex min-w-0 shrink items-center gap-1 whitespace-nowrap rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100 sm:ml-0 sm:gap-1.5 sm:px-3">
                    <x-icon name="utensils" class="hidden h-3.5 w-3.5 min-[370px]:block" />
                    <span class="truncate">Meja {{ $currentTable->number }}</span>
                    <span class="hidden font-medium text-brand-500 underline sm:inline">Ganti</span>
                </a>
            @endif
        </div>

        <nav class="hidden items-center gap-8 md:flex">
            @foreach ($nav as [$label, $url, $active])
                <a href="{{ $url }}"
                    class="border-b-2 py-5 text-sm font-semibold {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-900' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-0.5 sm:gap-3">
            @if ($currentTable)
                <a href="{{ route('cart') }}" class="relative rounded-full p-2 text-gray-600 hover:bg-gray-100" aria-label="Keranjang">
                    <x-icon name="bag" class="h-6 w-6" />
                    <span x-data x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count"
                        class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white"></span>
                </a>
            @endif

            @if ($user)
                {{-- HP: langsung ke profil (menu lengkap ada di drawer). --}}
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-1 hover:bg-gray-100 md:hidden" aria-label="Profil">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials }}</span>
                </a>

                {{-- Desktop: klik avatar membuka pilihan Profil dan Keluar. --}}
                <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative hidden md:block">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-gray-100">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials }}</span>
                        <span class="text-left leading-tight">
                            <span class="block text-sm font-semibold">{{ \Illuminate\Support\Str::limit($user->name, 16) }}</span>
                            <span class="block text-xs text-gray-500">{{ match ($user->role) {
                                \App\Enums\Role::Admin => 'Admin',
                                \App\Enums\Role::Staff => 'Karyawan',
                                default => 'Pelanggan',
                            } }}</span>
                        </span>
                    </button>

                    <div x-cloak x-show="open" x-transition.opacity.duration.150ms role="menu"
                        class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white p-1.5 shadow-lg">
                        <a href="{{ route('profile.edit') }}" role="menuitem"
                            class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <x-icon name="user" class="h-4 w-4 text-gray-400" /> Profil Saya
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                                <x-icon name="logout" class="h-4 w-4" /> Keluar dari Akun
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="whitespace-nowrap rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800 sm:px-4">
                    Masuk<span class="hidden sm:inline"> / Profil</span>
                </a>
            @endif
        </div>
    </div>

</header>

{{-- HP: drawer navigasi dari kiri. Sengaja tanpa link ke /admin atau /staff (PRD G-13). --}}
<div x-data="{ open: false }"
    @open-nav.window="open = true"
    @keydown.escape.window="open = false"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-cloak x-show="open" class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Menu navigasi">
    <div x-show="open"
        x-transition:enter="transition-opacity ease-out duration-300 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

    <aside x-show="open"
        x-transition:enter="transition-transform ease-out duration-300 motion-reduce:transition-none"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform ease-in duration-200 motion-reduce:transition-none"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
        class="absolute inset-y-0 left-0 flex w-[82%] max-w-xs flex-col overflow-y-auto bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <span class="text-xl font-extrabold tracking-tight text-brand-700">{{ config('app.name') }}</span>
            <button type="button" @click="open = false" aria-label="Tutup menu" class="rounded-full p-1.5 text-gray-500 hover:bg-gray-100">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        @if ($user)
            <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700">{{ $initials }}</span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate font-semibold">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ $user->email }}</span>
                </span>
            </div>
        @endif

        @php
            $drawerLinks = [
                ['Menu Utama', $menuUrl, 'utensils', request()->routeIs('menu', 'cart', 'home')],
                ['Riwayat Pesanan', route('riwayat'), 'receipt', request()->routeIs('riwayat', 'order.show')],
            ];

            if ($user) {
                $drawerLinks[] = ['Profil Saya', route('profile.edit'), 'user', request()->routeIs('profile.edit')];
                $drawerLinks[] = ['Ubah Kata Sandi', route('profile.edit').'#kata-sandi', 'lock', false];
            }

            if ($currentTable) {
                $drawerLinks[] = ['Ganti Meja (Meja '.$currentTable->number.')', route('table.change'), 'refresh', false];
            }
        @endphp

        <nav class="flex-1 px-3 py-3">
            @foreach ($drawerLinks as [$label, $url, $icon, $active])
                <a href="{{ $url }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ $active ? 'bg-brand-50 text-brand-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    <x-icon :name="$icon" class="h-5 w-5 {{ $active ? 'text-brand-600' : 'text-gray-400' }}" />
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-gray-100 p-4">
            @if ($user)
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-100">
                        <x-icon name="logout" class="h-4 w-4" /> Keluar dari Akun
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white hover:bg-brand-700">Masuk atau Daftar</a>
            @endif
        </div>
    </aside>
</div>
