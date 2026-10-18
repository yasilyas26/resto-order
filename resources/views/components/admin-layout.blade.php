@props(['title'])

@php
    $links = [
        ['Dashboard', 'admin.index', ['admin.index']],
        ['Kategori', 'admin.categories.index', ['admin.categories.*']],
        ['Menu', 'admin.menus.index', ['admin.menus.*']],
        ['Grup Opsi', 'admin.option-groups.index', ['admin.option-groups.*', 'admin.options.*']],
        ['Meja', 'admin.tables.index', ['admin.tables.*']],
        ['Sampah', 'admin.trash.index', ['admin.trash.*']],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
        <nav class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
            @foreach ($links as [$label, $route, $patterns])
                <a href="{{ route($route) }}"
                    class="{{ request()->routeIs(...$patterns) ? 'font-semibold text-gray-900 underline' : 'text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded bg-green-100 p-3 text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded bg-red-100 p-3 text-red-800">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-app-layout>
