/** Kolom password dengan tombol lihat/sembunyikan, dan (opsional) penunjuk kekuatan sesuai aturan server. */
export default function passwordField() {
    return {
        show: false,
        value: '',

        // Aturan yang sama dengan server: minimal 8, huruf besar & kecil, angka.
        // (Pengecekan "pernah bocor" hanya bisa dilakukan server.)
        get rules() {
            return [
                this.value.length >= 8,
                /[a-z]/.test(this.value) && /[A-Z]/.test(this.value),
                /\d/.test(this.value),
            ];
        },

        get score() {
            return this.value ? this.rules.filter(Boolean).length : 0;
        },

        get label() {
            return ['Belum terisi', 'Lemah', 'Cukup', 'Kuat'][this.score];
        },

        barClass(index) {
            if (this.score <= index) {
                return 'bg-brand-100';
            }

            return ['bg-red-500', 'bg-amber-500', 'bg-emerald-500'][this.score - 1];
        },
    };
}
