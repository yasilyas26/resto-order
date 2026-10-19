import { formatClock, offsetFrom, secondsLeft } from '../lib/countdown';
import { postJson } from '../lib/http';

/**
 * Halaman "Pilih Metode Pembayaran": pilihan dibuat lewat kelompok + dropdown ber-logo (bank dan e-wallet).
 * Server membuat transaksinya lewat Core API, lalu pelanggan langsung dibawa ke halaman hasil yang menampilkan
 * nomor VA atau QR-nya. Isi halaman hasil ditentukan status di server (webhook Midtrans), bukan callback browser.
 *
 * Batas waktu bayar baru mulai berjalan saat tombol Bayar ditekan (server menetapkannya), lalu dihitung
 * mundur di sini. Menutup popup atau berganti metode tidak mengulang waktunya.
 */
export default function paymentMethod({ payUrl, resultUrl, selected, group, choices, expiresAt, serverTime }) {
    // Pilihan terakhir pelanggan diisikan ke dropdown kelompoknya; kelompok lain mulai dari pilihan pertama.
    const initialChoices = { ...choices, [group]: selected };

    return {
        group,
        choices: initialChoices,
        /** Kunci metode yang dikirim ke server: pilihan di dropdown kelompok terpilih. */
        get selected() {
            return this.choices[this.group];
        },
        busy: false,
        message: '',
        expiresAt,
        expired: false, // true setelah hitungan habis, sampai pelanggan menekan Bayar lagi
        now: Date.now(),
        _offset: offsetFrom(serverTime),

        init() {
            setInterval(() => {
                this.now = Date.now();

                if (this.expiresAt && this.remaining === 0) {
                    this.expiresAt = null;
                    this.expired = true;
                }
            }, 1000);
        },

        get remaining() {
            void this.now; // reaktif terhadap detak jam

            return secondsLeft(this.expiresAt, this._offset);
        },

        get countdownLabel() {
            return this.remaining === null ? '' : formatClock(this.remaining);
        },

        async pay() {
            this.busy = true;
            this.message = '';

            try {
                const { ok, data } = await postJson(payUrl, { method: this.selected });

                if (!ok) {
                    // Ternyata sudah dibayar (mis. di jendela lain): langsung ke halaman hasil.
                    if (data.result_url) {
                        window.location.href = data.result_url;

                        return;
                    }

                    this.message = data.message || 'Pembayaran belum bisa dibuka. Coba lagi.';
                    this.busy = false;

                    return;
                }

                // Waktu bayar mulai (atau lanjut) dari sini, menurut server.
                this._offset = offsetFrom(data.server_time);
                this.expiresAt = data.expires_at;
                this.expired = false;

                // Transaksi sudah dibuat di Midtrans: nomor VA / QR-nya tampil di halaman hasil.
                window.location.href = data.result_url || resultUrl;
            } catch {
                this.message = 'Tidak bisa menghubungi server. Periksa koneksi internetmu.';
                this.busy = false;
            }
        },
    };
}
