import Alpine from 'alpinejs';
import { toPayload } from '../lib/cart-logic';
import { errorMessages, postJson } from '../lib/http';
import { rememberOrder } from '../lib/storage';

/** Form checkout: kirim keranjang ke server, lalu buka popup pembayaran Midtrans Snap. */
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

                this.openPayment(data);
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

        openPayment(data) {
            const go = () => (window.location.href = data.order_url);

            if (!window.snap) {
                // Skrip Midtrans gagal dimuat (koneksi/pemblokir iklan): lanjut ke halaman status yang punya tombol bayar.
                go();

                return;
            }

            // Callback ini hanya mengarahkan ke halaman status. Status lunas ditentukan webhook Midtrans, bukan browser.
            window.snap.pay(data.snap_token, {
                onSuccess: go,
                onPending: go,
                onError: go,
                onClose: go,
            });
        },
    };
}
