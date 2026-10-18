// Entri JavaScript halaman pelanggan (menu, keranjang, status, riwayat, login/daftar).
// Halaman admin memakai app.js terpisah, jadi bundel pelanggan tidak membebani admin dan sebaliknya.

import Alpine from 'alpinejs';

import cartStore from './visitor/stores/cart';
import checkout from './visitor/components/checkout';
import history from './visitor/components/history';
import menuModal from './visitor/components/menuModal';
import orderStatus from './visitor/components/orderStatus';
import passwordField from './visitor/components/passwordField';
import scanner from './visitor/components/scanner';
import { formatRupiah, lineTotal } from './visitor/lib/cart-logic';

window.Alpine = Alpine;

// Dipakai langsung di template Blade/Alpine: x-text="rupiah(...)"
Alpine.magic('rupiah', () => formatRupiah);
Alpine.magic('lineTotal', () => lineTotal);

Alpine.store('cart', cartStore());

Alpine.data('menuModal', menuModal);
Alpine.data('scanner', scanner);
Alpine.data('checkout', checkout);
Alpine.data('orderStatus', orderStatus);
Alpine.data('history', history);
Alpine.data('passwordField', passwordField);

Alpine.start();
