<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Meja {{ $table->number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased print:bg-white">
    {{-- Halaman ini berdiri sendiri (tanpa navigasi) supaya hasil cetaknya bersih seperti stiker. --}}
    <div class="mx-auto max-w-sm px-4 py-8">
        <div class="rounded-lg bg-white p-6 text-center shadow print:shadow-none">
            <div class="text-sm uppercase tracking-widest text-gray-500">Meja</div>
            <div class="text-5xl font-bold text-gray-900">{{ $table->number }}</div>

            <div class="mx-auto mt-4 w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $svg !!}</div>

            <div class="mt-4 text-base font-semibold text-gray-900">Scan untuk memesan</div>
            <div class="mt-1 font-mono text-xs tracking-wider text-gray-400">{{ $table->code }}</div>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-3 print:hidden">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700">Cetak</button>
            <a href="{{ route('admin.tables.qr.download', $table) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-50">Unduh SVG</a>
            <a href="{{ route('admin.tables.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Kembali</a>
        </div>

        <p class="mt-4 text-center text-xs text-gray-500 print:hidden">
            QR berisi: <span class="break-all font-mono">{{ $table->scanUrl() }}</span>
        </p>
    </div>
</body>
</html>
