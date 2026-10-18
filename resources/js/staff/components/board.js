import { formatRupiah } from '../lib/format';
import { getJson, postJson } from '../lib/http';
import {
    KITCHEN_LABEL,
    completeWarning,
    countByStatus,
    filterOrders,
    isReadyToDeliver,
    mergeOrder,
    sortBoard,
    unavailableWarning,
    waitLabel,
    waitMinutes,
    waitTone,
} from '../lib/board-logic';

/**
 * Papan order karyawan. Data dipolling tiap ±5 detik (keputusan proyek: polling, bukan WebSocket);
 * tiap aksi dikirim ke server yang membalas dengan kartu terbaru, jadi tampilan selalu mengikuti server.
 */
export default function board({ dataUrl, baseUrl, initial, pollMs = 5000 }) {
    return {
        orders: sortBoard(initial.orders),
        filter: 'all',
        now: Date.now(),
        busy: {},
        error: '',
        offline: false,
        syncedAt: new Date(),
        statusLabels: KITCHEN_LABEL,
        _poll: null,

        init() {
            // Jam di kartu ("Tunggu: 8 mnt") bergerak tanpa menunggu polling.
            setInterval(() => (this.now = Date.now()), 15000);
            this.schedule();

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.refresh();
                }
            });
        },

        schedule() {
            clearTimeout(this._poll);
            this._poll = setTimeout(async () => {
                if (!document.hidden) {
                    await this.refresh();
                }

                this.schedule();
            }, pollMs);
        },

        /** Polling dilewati selama ada aksi yang sedang berjalan, supaya kartunya tidak "melompat". */
        async refresh() {
            if (Object.values(this.busy).some(Boolean)) {
                return;
            }

            try {
                const { ok, data } = await getJson(dataUrl);

                if (ok) {
                    this.orders = sortBoard(data.orders);
                    this.syncedAt = new Date();
                    this.offline = false;
                }
            } catch {
                this.offline = true;
            }
        },

        get counts() {
            return countByStatus(this.orders);
        },

        get visible() {
            return filterOrders(this.orders, this.filter);
        },

        get activeCount() {
            return this.counts.queued + this.counts.cooking;
        },

        get syncedLabel() {
            return this.syncedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        // --- bantuan tampilan --------------------------------------------------
        minutes(order) {
            return waitMinutes(order.paidAt, this.now);
        },

        waitText(order) {
            return `Tunggu: ${waitLabel(this.minutes(order))}`;
        },

        waitClass(order) {
            return { normal: 'text-gray-500', warn: 'text-amber-600', late: 'font-bold text-red-600' }[waitTone(this.minutes(order))];
        },

        ready(order) {
            return isReadyToDeliver(order);
        },

        rupiah: formatRupiah,

        canMarkUnavailable(order) {
            return order.kitchen !== 'done' && order.refund !== 'refunded';
        },

        // --- aksi ke server ----------------------------------------------------
        start(order) {
            return this.act(order, 'start');
        },

        complete(order) {
            const warning = completeWarning(order);

            if (warning && !window.confirm(warning)) {
                return;
            }

            return this.act(order, 'complete');
        },

        toggleServed(order, item) {
            return this.act(order, `items/${item.id}/serve`, { served: !item.served });
        },

        toggleUnavailable(order, item) {
            if (!item.unavailable && !window.confirm(unavailableWarning(order, item, formatRupiah))) {
                return;
            }

            return this.act(order, `items/${item.id}/unavailable`, { unavailable: !item.unavailable });
        },

        async act(order, path, body = {}) {
            if (this.busy[order.code]) {
                return;
            }

            this.busy[order.code] = true;
            this.error = '';

            try {
                const { ok, data } = await postJson(`${baseUrl}/${order.code}/${path}`, body);

                if (ok) {
                    this.orders = sortBoard(mergeOrder(this.orders, data.order));
                } else {
                    // Biasanya karena karyawan lain sudah mengubah order ini: tampilkan alasannya, lalu muat ulang.
                    this.error = data.message || 'Aksi gagal. Coba lagi.';
                    this.busy[order.code] = false;
                    await this.refresh();
                }
            } catch {
                this.error = 'Tidak bisa menghubungi server. Periksa koneksi internet.';
            } finally {
                this.busy[order.code] = false;
            }
        },
    };
}
