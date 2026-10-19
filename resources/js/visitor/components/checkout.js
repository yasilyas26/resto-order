import Alpine from 'alpinejs';
import { toPayload } from '../lib/cart-logic';
import { errorMessages, postJson } from '../lib/http';
import { getDraftOrder, setDraftOrder } from '../lib/storage';

/** Form checkout: kirim keranjang ke server, lalu arahkan ke halaman pilih metode pembayaran. */
export default function checkout({ url }) {
    return {
        loading: false,
        errors: [],
        /** true begitu order tersimpan: layar kerangka tetap tampil sampai halaman berikutnya terbuka. */
        redirecting: false,

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
                    draft: getDraftOrder(),
                    items: toPayload(cart.items),
                });

                if (status === 409 && data.redirect) {
                    window.location.href = data.redirect;

                    return;
                }

                // Order tersimpan sebagai draft. Keranjang SENGAJA tidak dikosongkan: baru dikosongkan saat pelanggan
                // menekan Bayar di halaman berikutnya, jadi kalau dia kembali ke keranjang isinya masih ada.
                if (data.order_code) {
                    setDraftOrder(data.order_code);
                }

                if (!ok) {
                    this.errors = errorMessages(data, 'Pesanan belum bisa dibuat. Coba lagi.');

                    if (data.order_url) {
                        setTimeout(() => (window.location.href = data.order_url), 2500);
                    }

                    return;
                }

                // Order tersimpan: lanjut ke halaman Pilih Metode Pembayaran. Layar kerangka dipertahankan
                // (loading tidak dimatikan) sampai browser benar-benar pindah halaman.
                this.redirecting = true;
                window.location.href = data.checkout_url || data.order_url;
            } catch {
                this.errors = ['Tidak bisa menghubungi server. Periksa koneksi internetmu.'];
            } finally {
                // Kalau sedang berpindah halaman, layar kerangka dibiarkan sampai halaman berikutnya terbuka.
                if (!this.redirecting) {
                    this.loading = false;
                }
            }
        },
    };
}
