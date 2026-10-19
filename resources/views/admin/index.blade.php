<x-admin-layout title="Dashboard Admin">
    <p class="mb-6 text-gray-600">Halo, <span class="font-semibold text-gray-900">{{ auth()->user()->name }}</span>. Pilih bagian yang mau dikelola.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Kategori', 'Kelompok menu di halaman pemesanan.', 'admin.categories.index', 'utensils'],
            ['Menu', 'Nama, harga, foto, status tersedia/habis.', 'admin.menus.index', 'receipt'],
            ['Grup Opsi', 'Level pedas, topping, dan pilihan lain.', 'admin.option-groups.index', 'flame'],
            ['Meja', 'Kode meja, QR, aktif/nonaktif.', 'admin.tables.index', 'qr'],
            ['Sampah', 'Pulihkan atau hapus permanen.', 'admin.trash.index', 'trash'],
            ['Papan Karyawan', 'Order yang sudah dibayar, status dapur, dan refund.', 'staff.orders.index', 'bag'],
        ] as [$label, $desc, $route, $icon])
            <a href="{{ route($route) }}"
                class="group flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 group-hover:bg-brand-100">
                    <x-icon :name="$icon" class="h-5 w-5" />
                </span>
                <span>
                    <span class="block font-bold text-gray-900">{{ $label }}</span>
                    <span class="mt-1 block text-sm text-gray-500">{{ $desc }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-admin-layout>
