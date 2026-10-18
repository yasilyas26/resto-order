import Alpine from 'alpinejs';
import { toPayload } from '../lib/cart-logic';
import { errorMessages, postJson } from '../lib/http';
import { readJson, rememberOrder, writeJson } from '../lib/storage';

const CUSTOMER_KEY = 'resto.customer.v1';

/** Form checkout: kirim keranjang ke server, lalu buka popup pembayaran Midtrans Snap. */
export default function checkout({ url, defaultName = '' }) {
    return {
        name: '',
        contact: '',
        loading: false,
        errors: [],

        init() {
            const saved = readJson(CUSTOMER_KEY, {});
            this.name = defaultName || saved?.name || '';
            this.contact = saved?.contact || '';
        },

        get canSubmit() {
            return !this.loading && Alpine.store('cart').items.length > 0 && this.name.trim().length > 0;
        },

        async submit() {
            const cart = Alpine.store('cart');
            this.errors = [];

            if (!cart.items.length) {
                this.errors = ['Keranjang masih kosong.'];

                return;
            }

            if (!this.name.trim()) {
                this.errors = ['Nama pemesan wajib diisi.'];

                return;
            }

            this.loading = true;

            try {
                const { ok, status, data } = await postJson(url, {
                    customer_name: this.name.trim(),
                    customer_contact: this.contact.trim() || null,
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

                writeJson(CUSTOMER_KEY, { name: this.name.trim(), contact: this.contact.trim() });
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
