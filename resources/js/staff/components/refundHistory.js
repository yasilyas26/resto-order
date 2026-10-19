import { formatRupiah } from '../lib/format';
import { getJson, postJson } from '../lib/http';

const REFRESH_MS = 15000;

/** Tab Riwayat & Refund: order selesai, dan refund yang perlu atau sudah diserahkan kasir. */
export default function refundHistory({ dataUrl, refundBase, initial, filter }) {
    return {
        filter,
        orders: initial.orders,
        nextPage: initial.nextPage,
        counts: initial.counts,
        loading: false,
        busy: {},
        error: '',
        rupiah: formatRupiah,
        _timer: null,

        init() {
            this.schedule();
        },

        schedule() {
            clearTimeout(this._timer);
            this._timer = setTimeout(async () => {
                if (!document.hidden && !Object.values(this.busy).some(Boolean)) {
                    await this.load(true);
                }

                this.schedule();
            }, REFRESH_MS);
        },

        async setFilter(filter) {
            // Daftar lama dikosongkan supaya kerangka (skeleton) tampil selama saringan baru dimuat, bukan data saringan sebelumnya.
            if (filter !== this.filter) {
                this.orders = [];
                this.nextPage = null;
            }

            this.filter = filter;
            await this.load(true);
        },

        async load(reset = false) {
            this.loading = true;

            try {
                const page = reset ? 1 : this.nextPage;
                const { ok, data } = await getJson(`${dataUrl}?filter=${this.filter}&page=${page}`);

                if (ok) {
                    this.orders = reset ? data.orders : [...this.orders, ...data.orders];
                    this.nextPage = data.nextPage;
                    this.counts = data.counts;
                }
            } catch {
                // dicoba lagi di putaran berikutnya
            } finally {
                this.loading = false;
            }
        },

        async markRefunded(order) {
            if (this.busy[order.code]) {
                return;
            }

            if (!window.confirm(`Sudah menyerahkan ${formatRupiah(order.refundAmount)} ke pelanggan untuk order ${order.code}?`)) {
                return;
            }

            this.busy[order.code] = true;
            this.error = '';

            try {
                const { ok, data } = await postJson(`${refundBase}/${order.code}/refund`);

                if (ok) {
                    this.counts = data.counts;
                    await this.load(true);
                } else {
                    this.error = data.message || 'Gagal mencatat refund. Coba lagi.';
                    await this.load(true);
                }
            } catch {
                this.error = 'Tidak bisa menghubungi server. Periksa koneksi internet.';
            } finally {
                this.busy[order.code] = false;
            }
        },
    };
}
