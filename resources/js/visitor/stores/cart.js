import {
    addOrMerge,
    cartCount,
    cartSubtotal,
    reconcileCart,
    replaceItem,
    sanitizeStoredCart,
    setQuantity,
} from '../lib/cart-logic';
import { readJson, writeJson } from '../lib/storage';

const CART_KEY = 'resto.cart.v1';
const TYPE_KEY = 'resto.orderType.v1';

/**
 * Keranjang pengunjung, disimpan di localStorage supaya selamat dari refresh halaman.
 * Isinya hanya untuk tampilan; harga yang dibayar dihitung ulang di server.
 */
export default function cartStore() {
    return {
        items: [],
        orderType: 'dine_in',
        toast: null,
        _toastTimer: null,

        init() {
            this.load();

            // Tab lain mengubah keranjang: ikut diperbarui.
            window.addEventListener('storage', (event) => {
                if (event.key === CART_KEY || event.key === TYPE_KEY) {
                    this.load();
                }
            });
        },

        load() {
            this.items = sanitizeStoredCart(readJson(CART_KEY, []));
            const type = readJson(TYPE_KEY, 'dine_in');
            this.orderType = type === 'takeaway' ? 'takeaway' : 'dine_in';
        },

        save() {
            writeJson(CART_KEY, this.items);
        },

        get count() {
            return cartCount(this.items);
        },

        get subtotal() {
            return cartSubtotal(this.items);
        },

        add(item) {
            this.items = addOrMerge(this.items, item);
            this.save();
        },

        update(oldKey, item) {
            this.items = replaceItem(this.items, oldKey, item);
            this.save();
        },

        remove(key) {
            this.items = this.items.filter((item) => item.key !== key);
            this.save();
        },

        setQty(key, qty) {
            if (qty < 1) {
                this.remove(key);

                return;
            }

            this.items = setQuantity(this.items, key, qty);
            this.save();
        },

        clear() {
            this.items = [];
            this.save();
        },

        setOrderType(type) {
            this.orderType = type === 'takeaway' ? 'takeaway' : 'dine_in';
            writeJson(TYPE_KEY, this.orderType);
        },

        /** Cocokkan isi keranjang dengan menu terbaru; beri tahu kalau ada yang dibuang. */
        reconcile(menus) {
            const { items, removed } = reconcileCart(this.items, menus);
            this.items = items;
            this.save();

            if (removed.length) {
                this.flash(`Dihapus dari keranjang karena tidak tersedia lagi: ${removed.join(', ')}.`, 6000);
            }
        },

        flash(message, ms = 2500) {
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = null), ms);
        },
    };
}
