import { getJson, postJson } from '../lib/http';

const REFRESH_MS = 10000;

/**
 * Daftar riwayat order pelanggan yang login: halaman pertama datang dari server, sisanya lewat
 * "Muat lebih banyak". Order yang masih berjalan disegarkan otomatis.
 */
export default function history({ lookupUrl, dataUrl, initial = [], nextPage = null }) {
    return {
        orders: initial,
        nextPage,
        loading: false,
        loadingMore: false,
        _timer: null,

        init() {
            this.scheduleRefresh();
        },

        async loadMore() {
            if (!this.nextPage || this.loadingMore) {
                return;
            }

            this.loadingMore = true;

            try {
                const { ok, data } = await getJson(`${dataUrl}?page=${this.nextPage}`);

                if (ok) {
                    this.orders = [...this.orders, ...data.orders];
                    this.nextPage = data.nextPage;
                }
            } finally {
                this.loadingMore = false;
            }
        },

        scheduleRefresh() {
            clearTimeout(this._timer);
            this._timer = setTimeout(async () => {
                if (!document.hidden) {
                    await this.refreshActive();
                }

                this.scheduleRefresh();
            }, REFRESH_MS);
        },

        /** Segarkan hanya order yang masih berjalan. */
        async refreshActive() {
            const codes = this.orders.filter((o) => o.active).map((o) => o.code);

            if (!codes.length) {
                return;
            }

            try {
                const { ok, data } = await postJson(lookupUrl, { codes });

                if (ok) {
                    const fresh = new Map(data.orders.map((o) => [o.code, o]));
                    this.orders = this.orders.map((o) => fresh.get(o.code) ?? o);
                }
            } catch {
                // dicoba lagi di putaran berikutnya
            }
        },

        // --- bantuan tampilan ------------------------------------------------
        badgeClass(stage) {
            return (
                {
                    waiting: 'bg-amber-50 text-amber-800 border-amber-300',
                    paid: 'bg-sky-50 text-sky-800 border-sky-300',
                    cooking: 'bg-amber-50 text-amber-800 border-amber-300',
                    done: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    expired: 'bg-gray-100 text-gray-600 border-gray-200',
                    failed: 'bg-red-50 text-red-700 border-red-200',
                }[stage] ?? 'bg-gray-100 text-gray-600 border-gray-200'
            );
        },

        optionsSummary(order) {
            return order.items
                .map((i) => i.options)
                .filter(Boolean)
                .join(' · ');
        },

        itemChip(item) {
            return `${item.qty}x ${item.name}${item.options ? ` (${item.options})` : ''}`;
        },

        firstNote(order) {
            return order.items.find((i) => i.note)?.note ?? '';
        },
    };
}
