<x-admin-layout title="Dashboard Admin">
    <p class="mb-6 text-gray-600">Halo, {{ auth()->user()->name }}. Pilih bagian yang mau dikelola.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Kategori', 'Kelompok menu di halaman pemesanan.', 'admin.categories.index'],
            ['Menu', 'Nama, harga, foto, status tersedia/habis.', 'admin.menus.index'],
            ['Grup Opsi', 'Level pedas, topping, dan pilihan lain.', 'admin.option-groups.index'],
            ['Meja', 'Kode meja, QR, aktif/nonaktif.', 'admin.tables.index'],
            ['Sampah', 'Pulihkan atau hapus permanen.', 'admin.trash.index'],
            ['Papan Karyawan', 'Order yang sudah dibayar, status dapur, dan refund.', 'staff.orders.index'],
        ] as [$label, $desc, $route])
            <a href="{{ route($route) }}" class="block rounded-lg bg-white p-5 shadow hover:shadow-md">
                <div class="font-semibold text-gray-900">{{ $label }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ $desc }}</div>
            </a>
        @endforeach
    </div>
</x-admin-layout>
