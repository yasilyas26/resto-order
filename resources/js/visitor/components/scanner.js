/**
 * Scan QR meja memakai kamera browser (html5-qrcode).
 * Kamera hanya jalan di HTTPS atau localhost; di luar itu pengunjung diarahkan mengetik kode.
 */
export default function scanner() {
    return {
        open: false,
        starting: false,
        error: '',
        code: '',
        _reader: null,

        async start() {
            this.error = '';

            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.open = true;
                this.error = 'Kamera hanya bisa dipakai lewat koneksi aman (HTTPS). Silakan ketik kode meja di bawah.';

                return;
            }

            this.open = true;
            this.starting = true;

            try {
                // Dimuat saat dibutuhkan saja, supaya halaman awal tetap ringan.
                const { Html5Qrcode } = await import('html5-qrcode');

                this._reader = new Html5Qrcode('qr-reader');

                await this._reader.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (text) => this.onScanned(text),
                    () => {},
                );
            } catch (e) {
                this.error = 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser, atau ketik kode meja di bawah.';
                await this.stop();
            } finally {
                this.starting = false;
            }
        },

        async stop() {
            const reader = this._reader;
            this._reader = null;

            if (reader) {
                try {
                    await reader.stop();
                    reader.clear();
                } catch {
                    // kamera sudah berhenti
                }
            }
        },

        async close() {
            await this.stop();
            this.open = false;
            this.error = '';
        },

        async onScanned(text) {
            const code = extractTableCode(text);

            if (!code) {
                this.error = 'QR ini bukan QR meja. Coba scan QR yang ada di mejamu.';

                return;
            }

            await this.stop();
            // Selalu ke alamat kita sendiri, apa pun isi QR-nya.
            window.location.href = `/m/${encodeURIComponent(code)}`;
        },
    };
}

/** Ambil kode meja dari isi QR: URL ".../m/KODE" atau kode polos "A07-K3F9". Selain itu ditolak. */
export function extractTableCode(text) {
    const value = String(text ?? '').trim();
    const fromUrl = value.match(/\/m\/([A-Za-z0-9-]{3,30})\/?(?:[?#].*)?$/);

    if (fromUrl) {
        return fromUrl[1].toUpperCase();
    }

    return /^[A-Za-z0-9-]{3,30}$/.test(value) ? value.toUpperCase() : null;
}
