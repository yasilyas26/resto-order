{{-- Tahun mengikuti tanggal server, jadi tidak perlu diganti tiap tahun. Nama perusahaan dari APP_NAME. --}}
<footer class="border-t border-gray-200 bg-white px-4 py-5 text-center text-xs text-gray-500">
    &copy; {{ now()->year }} PT. {{ config('app.name') }}. All Rights Reserved
</footer>
