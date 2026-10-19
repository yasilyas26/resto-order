import { getJson, postJson } from '../lib/http';
import { rememberOrder } from '../lib/storage';

const POLL_MS = 5000; // keputusan proyek: polling ±5 detik, bukan WebSocket

/** Halaman status order: polling status, tombol bayar, cek ulang pembayaran, salin kode. */
export default function orderStatus({ code, table, statusUrl, recheckUrl, payUrl, changeUrl, initial }) {
    return {
        code,
        table,
        status: initial,
        busy: false,
        message: '',
        copied: false,
        _timer: null,

        init() {
            // Order ini selalu masuk riwayat perangkat (juga kalau halamannya dibuka dari tautan).
            rememberOrder(code);
            this.schedule();
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.refresh();
                }
            });
        },

        /** Polling hanya selama order masih berjalan dan tab sedang dibuka. */
        get polling() {
            return ['waiting', 'paid', 'cooking'].includes(this.status.stage);
        },

        schedule() {
            clearTimeout(this._timer);
            this._timer = setTimeout(async () => {
                if (this.polling && !document.hidden) {
                    await this.refresh();
                }

                this.schedule();
            }, POLL_MS);
        },

        async refresh() {
            try {
                const { ok, data } = await getJson(statusUrl);

                if (ok) {
                    this.status = data;
                }
            } catch {
                // koneksi putus sebentar; percobaan berikutnya jalan sendiri
            }
        },

        /** Tanya Midtrans lewat server, untuk kasus webhook telat. */
        async recheck() {
            this.busy = true;
            this.message = '';

            try {
                const { ok, data } = await postJson(recheckUrl);

                if (ok) {
                    this.status = data;
                    this.message = data.paid ? '' : 'Pembayaran belum tercatat. Kalau sudah membayar, tunggu sebentar lalu cek lagi.';
                }
            } catch {
                this.message = 'Tidak bisa menghubungi server. Periksa koneksi internetmu.';
            } finally {
                this.busy = false;
            }
        },

        /** Ganti metode bayar (mis. QRIS ke kartu): server membatalkan transaksi lama lalu membuka yang baru. */
        changeMethod() {
            return this.pay(changeUrl);
        },

        async pay(url = payUrl) {
            this.busy = true;
            this.message = '';

            try {
                const { ok, data } = await postJson(url);

                if (!ok) {
                    this.message = data.message || 'Pembayaran belum bisa dibuka. Coba lagi.';

                    // Ternyata sudah dibayar (mis. di jendela lain): ambil status terbaru supaya halaman ikut berubah.
                    if (url === changeUrl && data.message?.includes('sudah kami terima')) {
                        await this.recheck();
                    }

                    return;
                }

                if (!window.snap) {
                    this.message = 'Jendela pembayaran gagal dimuat. Muat ulang halaman lalu coba lagi.';

                    return;
                }

                const done = () => this.recheck();

                window.snap.pay(data.snap_token, {
                    onSuccess: done,
                    onPending: done,
                    onError: done,
                    onClose: done,
                });
            } catch {
                this.message = 'Tidak bisa menghubungi server. Periksa koneksi internetmu.';
            } finally {
                this.busy = false;
            }
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.code);
            } catch {
                // clipboard diblokir (mis. HTTP biasa): pakai cara lama
                const input = document.createElement('input');
                input.value = this.code;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }

            this.copied = true;
            setTimeout(() => (this.copied = false), 1800);
        },

        // --- bantuan tampilan garis waktu -----------------------------------
        stepState(step) {
            const current = this.status.step;

            if (current === 0) {
                return step === 1 ? 'problem' : 'todo';
            }

            if (step < current || (this.status.stage === 'done' && step === 4)) {
                return 'done';
            }

            return step === current ? 'current' : 'todo';
        },

        /** Keterangan kecil di bawah tiap langkah garis waktu. */
        subLabel(step) {
            const s = this.status;
            const state = this.stepState(step);

            if (step === 1) {
                return { done: 'Selesai', current: 'Menunggu pembayaran', problem: s.label, todo: '' }[state];
            }

            if (step === 2) {
                if (state === 'todo' || state === 'problem') {
                    return '';
                }

                return `Lunas${s.paymentLabel ? ` via ${s.paymentLabel}` : ''}${s.paidAt ? ` (${s.paidAt})` : ''}`;
            }

            if (step === 3) {
                return { current: 'Sedang diproses dapur', done: 'Selesai dimasak' }[state] ?? '';
            }

            return state === 'done' ? 'Siap diantar' : this.table ? `Diantar ke Meja ${this.table}` : '';
        },
    };
}
