{{-- Pilihan Makan di Tempat / Bawa Pulang. Dipakai di ringkasan menu dan halaman keranjang. --}}
<div class="inline-flex shrink-0 rounded-full bg-gray-100 p-0.5 text-xs font-semibold" role="group" aria-label="Jenis pesanan">
    <button type="button" @click="$store.cart.setOrderType('dine_in')"
        class="whitespace-nowrap rounded-full px-3 py-1 transition"
        :class="$store.cart.orderType === 'dine_in' ? 'bg-brand-700 text-white shadow' : 'text-gray-500'">Makan di Tempat</button>
    <button type="button" @click="$store.cart.setOrderType('takeaway')"
        class="whitespace-nowrap rounded-full px-3 py-1 transition"
        :class="$store.cart.orderType === 'takeaway' ? 'bg-brand-700 text-white shadow' : 'text-gray-500'">Bawa Pulang</button>
</div>
