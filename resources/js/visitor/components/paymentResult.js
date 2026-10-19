import { formatClock, offsetFrom, secondsLeft } from '../lib/countdown';
import { getJson, postJson } from '../lib/http';

const POLL_MS = 3000;
const SLOW_AFTER = 10; // setelah ±30 detik tanpa kabar, tampilkan petunjuk
const GIVE_UP_AFTER = 100; // ±5 menit; setelah itu berhenti memeriksa

/**
 * Halaman hasil pembayaran. Selama status masih "menunggu", status diperiksa tiap beberapa detik
 * dan Midtrans ditanya langsung sesekali (webhook bisa telat, atau tidak sampai di lingkungan lokal).
 * Begitu statusnya berubah, atau hitung mundur habis, halaman dimuat ulang supaya tampil sebagai hasil yang benar.
 * Cara bayar (QR, nomor VA, kode bayar) ikut diperbarui dari balasan yang sama.
 */
export default function paymentResult({ state, statusUrl, recheckUrl, expiresAt, serverTime, instructions }) {
    return {
        slow: false,
        now: Date.now(),
        instructions,
        // true selama pemeriksaan pertama ke Midtrans berjalan dan petunjuk bayar belum ada: tampilkan kerangka.
        checking: state === 'waiting' && !instructions,
        copied: '',
        _tries: 0,
        _offset: offsetFrom(serverTime),

        init() {
            if (state !== 'waiting') {
                return;
            }

            // Pertama kali masuk (mis. baru menutup popup), langsung tanya Midtrans supaya cara bayar cepat tampil.
            this.check(true);
            this._timer = setInterval(() => this.check(), POLL_MS);

            setInterval(() => {
                this.now = Date.now();

                // Batas bayar lewat: server sudah menganggapnya kedaluwarsa, jadi muat ulang untuk melihatnya.
                if (expiresAt && this.remaining === 0) {
                    window.location.reload();
                }
            }, 1000);
        },

        get remaining() {
            void this.now; // reaktif terhadap detak jam

            return secondsLeft(expiresAt, this._offset);
        },

        get countdownLabel() {
            return this.remaining === null ? '' : formatClock(this.remaining);
        },

        async check(forceRecheck = false) {
            if (!forceRecheck) {
                this._tries++;
            }

            if (this._tries > GIVE_UP_AFTER) {
                clearInterval(this._timer);

                return;
            }

            this.slow = this._tries >= SLOW_AFTER;

            try {
                // Tiap 3 putaran, minta server mengecek langsung ke Midtrans.
                const recheck = forceRecheck || this._tries % 3 === 0;
                const { ok, data } = recheck ? await postJson(recheckUrl) : await getJson(statusUrl);

                if (!ok) {
                    return;
                }

                if (data.stage !== 'waiting') {
                    clearInterval(this._timer);
                    window.location.reload();

                    return;
                }

                this.instructions = data.instructions ?? this.instructions;
            } catch {
                // koneksi putus sebentar: coba lagi di putaran berikutnya
            } finally {
                this.checking = false;
            }
        },

        async copyValue(value) {
            try {
                await navigator.clipboard.writeText(value);
            } catch {
                // clipboard diblokir (mis. HTTP biasa): pakai cara lama
                const input = document.createElement('input');
                input.value = value;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }

            this.copied = value;
            setTimeout(() => (this.copied = ''), 1800);
        },
    };
}
