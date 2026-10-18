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

            <div class="mt-4 text-sm text-gray-600">Scan untuk memesan, atau ketik kode:</div>
            <div class="mt-1 font-mono text-2xl font-semibold tracking-wider">{{ $table->code }}</div>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-3 print:hidden">
            <button onclick="window.print()" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Cetak</button>
            <a href="{{ route('admin.tables.qr.download', $table) }}" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm">Unduh SVG</a>
            <a href="{{ route('admin.tables.index') }}" class="text-sm text-gray-600 underline">Kembali</a>
        </div>

        <p class="mt-4 text-center text-xs text-gray-500 print:hidden">
            QR berisi: <span class="break-all font-mono">{{ $table->scanUrl() }}</span>
        </p>
    </div>
</body>
</html>
