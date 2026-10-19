// Pengganti window.confirm() di halaman admin. Form apa pun yang diberi atribut data-confirm
// akan menampilkan modal dulu; baru dikirim setelah pengguna menekan tombol konfirmasi.
//   data-confirm        isi pesan
//   data-confirm-title  judul modal
//   data-confirm-name   nama data yang dihapus (opsional, ditebalkan)
//   data-confirm-label  tulisan tombol konfirmasi
//   data-confirm-tone   "soft" (pindah ke Sampah / bisa dibatalkan) atau "hard" (hapus permanen)
export default () => ({
    open: false,
    form: null,
    title: '',
    message: '',
    name: '',
    label: 'Ya, lanjutkan',
    hard: false,

    intercept(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
            return;
        }

        event.preventDefault();

        this.form = form;
        this.title = form.dataset.confirmTitle || 'Yakin?';
        this.message = form.dataset.confirm;
        this.name = form.dataset.confirmName || '';
        this.label = form.dataset.confirmLabel || 'Ya, lanjutkan';
        this.hard = form.dataset.confirmTone === 'hard';
        this.open = true;

        this.$nextTick(() => this.$refs.cancel?.focus());
    },

    close() {
        this.open = false;
        this.form = null;
    },

    accept() {
        const form = this.form;
        this.close();
        // form.submit() tidak memicu event submit lagi, jadi tidak muncul modal dua kali.
        form?.submit();
    },
});
