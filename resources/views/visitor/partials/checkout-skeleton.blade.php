{{-- Kerangka halaman Pilih Metode Pembayaran. Menutup layar selama pesanan dibuat dan halaman berikutnya dimuat,
     supaya tidak ada jeda kosong atau keranjang yang tampak kosong di tengah jalan. --}}
<div class="fixed inset-0 z-[70] overflow-y-auto bg-gray-100" role="status" aria-busy="true" aria-label="Menyiapkan pembayaran">
    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <x-skeleton class="h-4 w-48" />
        <x-skeleton class="mt-4 h-8 w-72 max-w-full" />
        <x-skeleton class="mt-2 h-4 w-40" />

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
            {{-- Ringkasan pesanan --}}
            <div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between"><x-skeleton class="h-6 w-44" /><x-skeleton class="h-6 w-16 rounded-full" /></div>
                @foreach (range(1, 3) as $i)
                    <div class="flex gap-3 border-t border-gray-100 pt-4 sm:gap-4">
                        <x-skeleton class="h-16 w-16 shrink-0 rounded-xl sm:h-24 sm:w-24" />
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex justify-between gap-3"><x-skeleton class="h-5 w-2/5" /><x-skeleton class="h-5 w-20" /></div>
                            <x-skeleton class="h-4 w-4/5" />
                            <div class="flex gap-2"><x-skeleton class="h-5 w-24 rounded-full" /><x-skeleton class="h-5 w-20 rounded-full" /></div>
                        </div>
                    </div>
                @endforeach
                <div class="space-y-2 border-t border-gray-100 pt-4">
                    <div class="flex justify-between"><x-skeleton class="h-4 w-20" /><x-skeleton class="h-4 w-24" /></div>
                    <div class="flex justify-between"><x-skeleton class="h-5 w-16" /><x-skeleton class="h-6 w-28" /></div>
                </div>
            </div>

            {{-- Metode pembayaran + tombol --}}
            <div class="space-y-4">
                <div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                    <x-skeleton class="h-5 w-40" />
                    <x-skeleton class="h-16 w-full rounded-xl" />
                    <x-skeleton class="h-16 w-full rounded-xl" />
                </div>
                <div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex justify-between"><x-skeleton class="h-5 w-24" /><x-skeleton class="h-7 w-32" /></div>
                    <x-skeleton class="h-14 w-full rounded-xl" />
                </div>
            </div>
        </div>
    </div>
</div>
