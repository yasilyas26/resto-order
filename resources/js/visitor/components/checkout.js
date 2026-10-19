import Alpine from 'alpinejs';
import { toPayload } from '../lib/cart-logic';
import { errorMessages, postJson } from '../lib/http';
import { rememberOrder } from '../lib/storage';

/** Form checkout: kirim keranjang ke server, lalu arahkan ke halaman pilih metode pembayaran. */
export default function checkout({ url }) {
    return {
        loading: false,
        errors: [],

        get canSubmit() {
            return !this.loading && Alpine.store('cart').items.length > 0;
        },

        async submit() {
            const cart = Alpine.store('cart');
            this.errors = [];

            if (!cart.items.length) {
                this.errors = ['Keranjang masih kosong.'];

                return;
            }

            this.loading = true;

            try {
                const { ok, status, data } = await postJson(url, {
                    order_type: cart.orderType,
                    items: toPayload(cart.items),
                });

                if (status === 409 && data.redirect) {
                    window.location.href = data.redirect;

                    return;
                }

                // Order sudah tersimpan (walau pembayaran belum bisa dibuka): keranjang dikosongkan dan
                // pengunjung diarahkan ke halaman status, tempat dia bisa mencoba bayar lagi.
                if (data.order_code) {
                    this.afterOrderCreated(data);
                }

                if (!ok) {
                    this.errors = errorMessages(data, 'Pesanan belum bisa dibuat. Coba lagi.');

                    if (data.order_url) {
                        setTimeout(() => (window.location.href = data.order_url), 2500);
                    }

                    return;
                }

                // Order tersimpan: lanjut ke halaman Pilih Metode Pembayaran (popup Snap dibuka dari sana).
                window.location.href = data.checkout_url || data.order_url;
            } catch {
                this.errors = ['Tidak bisa menghubungi server. Periksa koneksi internetmu.'];
            } finally {
                this.loading = false;
            }
        },

        afterOrderCreated(data) {
            rememberOrder(data.order_code);
            Alpine.store('cart').clear();
        },
    };
}
