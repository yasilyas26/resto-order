import Alpine from 'alpinejs';
import {
    NOTE_MAX,
    buildCartItem,
    clampQty,
    defaultSelection,
    formatRupiah,
    toggleOption,
    unitTotal,
    validateSelection,
} from '../lib/cart-logic';

/**
 * Modal pilih opsi sebuah menu (level pedas, topping, jumlah, catatan).
 * Dibuka lewat event window "open-menu" dengan {id} (menambah) atau {id, key} (mengubah item di keranjang).
 */
export default function menuModal(menus) {
    return {
        menus,
        menu: null,
        selected: [],
        qty: 1,
        note: '',
        editKey: null,
        submitted: false,
        noteMax: NOTE_MAX,

        init() {
            // Menu terbaru sudah ada di halaman ini: samakan isi keranjang lama (harga naik, menu habis/dihapus).
            Alpine.store('cart').reconcile(this.menus);

            // Halaman di belakang modal tidak ikut bergulir.
            this.$watch('menu', (value) => document.body.classList.toggle('overflow-hidden', Boolean(value)));
        },

        open({ id, key = null }) {
            const menu = this.menus[id];

            if (!menu || !menu.available) {
                return;
            }

            const existing = key ? Alpine.store('cart').items.find((item) => item.key === key) : null;

            this.menu = menu;
            this.editKey = existing ? key : null;
            this.selected = existing ? existing.options.map((o) => o.id) : defaultSelection(menu);
            this.qty = existing ? existing.qty : 1;
            this.note = existing ? existing.note : '';
            this.submitted = false;
        },

        close() {
            this.menu = null;
        },

        get errors() {
            return this.menu ? validateSelection(this.menu, this.selected) : {};
        },

        get valid() {
            return Object.keys(this.errors).length === 0;
        },

        get total() {
            if (!this.menu) {
                return 0;
            }

            return unitTotal(buildCartItem(this.menu, this.selected, 1, '')) * this.qty;
        },

        get totalLabel() {
            return formatRupiah(this.total);
        },

        isSelected(optionId) {
            return this.selected.includes(optionId);
        },

        toggle(group, option) {
            this.selected = toggleOption(this.menu, this.selected, group.id, option.id);
        },

        changeQty(delta) {
            // Tidak boleh melebihi sisa stok (null = tidak dibatasi). Server tetap memeriksa ulang.
            const max = this.menu?.stock ?? Infinity;

            this.qty = Math.min(clampQty(this.qty + delta), Math.max(1, max));
        },

        priceLabel(price) {
            return price > 0 ? `+${formatRupiah(price)}` : 'Gratis';
        },

        rule(group) {
            if (group.max === 1) {
                return group.min >= 1 ? 'Pilih 1' : 'Boleh dikosongkan';
            }

            return group.min >= 1 ? `Pilih ${group.min}–${group.max}` : `Maksimal pilih ${group.max}`;
        },

        submit() {
            this.submitted = true;

            if (!this.valid) {
                return;
            }

            const item = buildCartItem(this.menu, this.selected, this.qty, this.note);
            const cart = Alpine.store('cart');

            if (this.editKey) {
                cart.update(this.editKey, item);
                cart.flash('Perubahan disimpan.');
            } else {
                cart.add(item);
                cart.flash(`${item.name} ditambahkan ke keranjang.`);
            }

            this.close();
        },
    };
}
