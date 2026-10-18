import test from 'node:test';
import assert from 'node:assert/strict';

import {
    addOrMerge,
    buildCartItem,
    cartCount,
    cartSubtotal,
    clampQty,
    defaultSelection,
    formatRupiah,
    itemKey,
    lineTotal,
    reconcileCart,
    replaceItem,
    resolveSelection,
    sanitizeStoredCart,
    setQuantity,
    toPayload,
    toggleOption,
    validateSelection,
} from '../../resources/js/visitor/lib/cart-logic.js';

// Menu "Mie Pedas": Level pedas wajib pilih 1 (id 1-4), Topping opsional maksimal 2 (id 10-12, 12 habis).
const menu = () => ({
    id: 7,
    name: 'Mie Pedas',
    image: null,
    category: 'Mie',
    price: 15000,
    available: true,
    groups: [
        {
            id: 1,
            name: 'Level pedas',
            min: 1,
            max: 1,
            options: [
                { id: 1, name: 'Level 0', price: 0, available: true },
                { id: 2, name: 'Level 1', price: 0, available: true },
                { id: 3, name: 'Level 3', price: 1000, available: true },
                { id: 4, name: 'Level 4', price: 1000, available: true },
            ],
        },
        {
            id: 2,
            name: 'Topping',
            min: 0,
            max: 2,
            options: [
                { id: 10, name: 'Telur', price: 3000, available: true },
                { id: 11, name: 'Keju', price: 4000, available: true },
                { id: 12, name: 'Bakso', price: 5000, available: false },
            ],
        },
    ],
});

test('formatRupiah memakai titik sebagai pemisah ribuan', () => {
    const nbsp = ' '; // spasi setelah "Rp" non-breaking, supaya tidak terpisah di akhir baris

    assert.equal(formatRupiah(15000), `Rp${nbsp}15.000`);
    assert.equal(formatRupiah(0), `Rp${nbsp}0`);
    assert.equal(formatRupiah(1250000), `Rp${nbsp}1.250.000`);
    assert.equal(formatRupiah('abc'), `Rp${nbsp}0`);
});

test('clampQty menjaga jumlah di antara 1 dan 50', () => {
    assert.equal(clampQty(0), 1);
    assert.equal(clampQty(-4), 1);
    assert.equal(clampQty('abc'), 1);
    assert.equal(clampQty(3), 3);
    assert.equal(clampQty(999), 50);
});

test('grup wajib pilih-satu langsung terisi opsi pertama yang tersedia', () => {
    assert.deepEqual(defaultSelection(menu()), [1]);
});

test('validateSelection menolak grup wajib yang kosong dan kelebihan pilihan', () => {
    const m = menu();

    assert.ok(validateSelection(m, [])[1], 'Level pedas wajib');
    assert.deepEqual(validateSelection(m, [1]), {});
    assert.deepEqual(validateSelection(m, [1, 10, 11]), {});
    assert.ok(validateSelection(m, [1, 10, 11, 12])[2], 'topping melebihi maksimal');
});

test('toggleOption: grup pilih-satu mengganti pilihan dan tidak bisa dikosongkan kalau wajib', () => {
    const m = menu();

    assert.deepEqual(toggleOption(m, [1], 1, 3), [3]);
    assert.deepEqual(toggleOption(m, [3], 1, 3), [3], 'mengetuk pilihan yang sama tidak mengosongkan grup wajib');
    assert.deepEqual(toggleOption(m, [1, 10], 1, 4), [10, 4], 'pilihan grup lain tidak terganggu');
});

test('toggleOption: grup pilih-banyak menambah, mengurangi, dan berhenti di batas maksimal', () => {
    const m = menu();

    assert.deepEqual(toggleOption(m, [1], 2, 10), [1, 10]);
    assert.deepEqual(toggleOption(m, [1, 10], 2, 11), [1, 10, 11]);
    assert.deepEqual(toggleOption(m, [1, 10, 11], 2, 10), [1, 11], 'mengetuk lagi melepas pilihan');
    assert.deepEqual(toggleOption(m, [1, 10, 11], 2, 12), [1, 10, 11], 'opsi habis tidak bisa dipilih');
});

test('toggleOption: grup tidak wajib pilih-satu boleh dikosongkan', () => {
    const m = menu();
    m.groups[0].min = 0;

    assert.deepEqual(toggleOption(m, [3], 1, 3), []);
});

test('toggleOption mengabaikan opsi yang bukan milik menu', () => {
    assert.deepEqual(toggleOption(menu(), [1], 1, 999), [1]);
    assert.deepEqual(toggleOption(menu(), [1], 99, 10), [1]);
});

test('harga item = (harga menu + tambahan opsi) x jumlah', () => {
    // 15.000 + 1.000 (Level 3) + 3.000 (Telur) + 4.000 (Keju) = 23.000
    const item = buildCartItem(menu(), [3, 10, 11], 2, '');

    assert.equal(lineTotal(item), 46000);
    assert.deepEqual(item.options.map((o) => o.name), ['Level 3', 'Telur', 'Keju']);
});

test('buildCartItem merapikan catatan dan jumlah', () => {
    const item = buildCartItem(menu(), [1], 500, '   tanpa bawang   ');

    assert.equal(item.note, 'tanpa bawang');
    assert.equal(item.qty, 50);
    assert.equal(buildCartItem(menu(), [1], 1, 'x'.repeat(500)).note.length, 200);
});

test('itemKey tidak peduli urutan opsi dan besar-kecil catatan', () => {
    assert.equal(itemKey(7, [10, 3], 'Tanpa Bawang '), itemKey(7, [3, 10], 'tanpa bawang'));
    assert.notEqual(itemKey(7, [3], ''), itemKey(7, [4], ''));
    assert.notEqual(itemKey(7, [3], ''), itemKey(8, [3], ''));
});

test('menambah item yang sama menggabungkan jumlahnya, yang beda jadi baris baru', () => {
    const a = buildCartItem(menu(), [1], 1, '');
    const sameAgain = buildCartItem(menu(), [1], 2, '');
    const other = buildCartItem(menu(), [3], 1, '');

    let items = addOrMerge([], a);
    items = addOrMerge(items, sameAgain);
    items = addOrMerge(items, other);

    assert.equal(items.length, 2);
    assert.equal(items[0].qty, 3);
    assert.equal(cartCount(items), 4);
});

test('jumlah gabungan tidak melewati batas 50', () => {
    const items = addOrMerge([buildCartItem(menu(), [1], 40, '')], buildCartItem(menu(), [1], 40, ''));

    assert.equal(items[0].qty, 50);
});

test('replaceItem (ubah item) bisa menggabung ke baris lain yang jadi identik', () => {
    const a = buildCartItem(menu(), [1], 1, '');
    const b = buildCartItem(menu(), [3], 2, '');
    const items = [a, b];

    // b diubah menjadi Level 0 (sama dengan a): digabung jadi satu baris berjumlah 3
    const changed = replaceItem(items, b.key, buildCartItem(menu(), [1], 2, ''));

    assert.equal(changed.length, 1);
    assert.equal(changed[0].qty, 3);
});

test('subtotal keranjang menjumlahkan semua baris', () => {
    const items = [buildCartItem(menu(), [1], 2, ''), buildCartItem(menu(), [3, 10], 1, '')];

    // 2 x 15.000 + (15.000 + 1.000 + 3.000)
    assert.equal(cartSubtotal(items), 30000 + 19000);
});

test('setQuantity mengubah satu baris saja', () => {
    const a = buildCartItem(menu(), [1], 1, '');
    const b = buildCartItem(menu(), [3], 1, '');

    const items = setQuantity([a, b], a.key, 4);

    assert.equal(items[0].qty, 4);
    assert.equal(items[1].qty, 1);
});

test('payload ke server hanya berisi ID, jumlah, dan catatan (tanpa harga)', () => {
    const payload = toPayload([buildCartItem(menu(), [3, 10], 2, 'pedas banget')]);

    assert.deepEqual(payload, [{ menu_id: 7, qty: 2, note: 'pedas banget', options: [3, 10] }]);
    assert.ok(!JSON.stringify(payload).includes('price'));
    assert.ok(!JSON.stringify(payload).includes('15000'));
});

test('sanitizeStoredCart membuang data rusak dan menghitung ulang kunci', () => {
    const good = buildCartItem(menu(), [1], 2, '');
    const cleaned = sanitizeStoredCart([
        good,
        null,
        'sampah',
        { menuId: 'bukan angka', name: 'x', options: [] },
        { menuId: 1, name: 'Tanpa opsi' },
        { menuId: 2, name: 'Qty aneh', price: '9000', options: [], qty: 'abc', key: 'palsu' },
    ]);

    assert.equal(cleaned.length, 2);
    assert.equal(cleaned[1].qty, 1);
    assert.equal(cleaned[1].price, 9000);
    assert.notEqual(cleaned[1].key, 'palsu');
    assert.deepEqual(sanitizeStoredCart('bukan array'), []);
    assert.deepEqual(sanitizeStoredCart(null), []);
});

test('reconcileCart memutakhirkan harga dan membuang menu yang habis atau berubah', () => {
    const fresh = menu();
    const ok = buildCartItem(menu(), [3], 1, '');
    const soldOut = buildCartItem({ ...menu(), id: 8, name: 'Menu Habis' }, [1], 1, '');
    const gone = buildCartItem({ ...menu(), id: 9, name: 'Menu Dihapus' }, [1], 1, '');
    const staleOption = buildCartItem(menu(), [12], 1, '');

    fresh.price = 17000; // harga naik sejak item masuk keranjang
    const menus = {
        7: fresh,
        8: { ...menu(), id: 8, available: false },
    };

    const result = reconcileCart([ok, soldOut, gone, staleOption], menus);

    assert.equal(result.items.length, 1);
    assert.equal(result.items[0].price, 17000, 'harga mengikuti menu terbaru');
    assert.deepEqual(result.removed.sort(), ['Menu Dihapus', 'Menu Habis', 'Mie Pedas'].sort());
});

test('resolveSelection mengikuti urutan grup di menu', () => {
    const picked = resolveSelection(menu(), [11, 3]);

    assert.deepEqual(picked.map((o) => o.id), [3, 11]);
    assert.equal(picked[0].groupName, 'Level pedas');
});
