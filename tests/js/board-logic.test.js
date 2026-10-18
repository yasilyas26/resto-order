import test from 'node:test';
import assert from 'node:assert/strict';

import {
    completeWarning,
    countByStatus,
    filterOrders,
    isReadyToDeliver,
    mergeOrder,
    sortBoard,
    unavailableWarning,
    unservedCount,
    waitLabel,
    waitMinutes,
    waitTone,
} from '../../resources/js/staff/lib/board-logic.js';
import { formatRupiah } from '../../resources/js/staff/lib/format.js';

const order = (overrides = {}) => ({
    code: 'ORD-AAAAAA',
    kitchen: 'queued',
    paidAt: '2026-10-19T10:00:00+07:00',
    servedCount: 0,
    servableCount: 2,
    ...overrides,
});

const at = (iso) => Date.parse(iso);

test('waitMinutes menghitung menit sejak dibayar dan tidak pernah negatif', () => {
    const paid = '2026-10-19T10:00:00+07:00';

    assert.equal(waitMinutes(paid, at('2026-10-19T10:08:30+07:00')), 8);
    assert.equal(waitMinutes(paid, at('2026-10-19T10:00:00+07:00')), 0);
    assert.equal(waitMinutes(paid, at('2026-10-19T09:59:00+07:00')), 0, 'jam perangkat mundur sedikit');
    assert.equal(waitMinutes('bukan tanggal', Date.now()), 0);
    assert.equal(waitMinutes(null, Date.now()), 0);
});

test('waitLabel memakai menit lalu jam', () => {
    assert.equal(waitLabel(0), '0 mnt');
    assert.equal(waitLabel(59), '59 mnt');
    assert.equal(waitLabel(60), '1 j');
    assert.equal(waitLabel(95), '1 j 35 mnt');
});

test('waitTone makin mendesak seiring lama menunggu', () => {
    assert.equal(waitTone(0), 'normal');
    assert.equal(waitTone(9), 'normal');
    assert.equal(waitTone(10), 'warn');
    assert.equal(waitTone(19), 'warn');
    assert.equal(waitTone(20), 'late');
});

test('siap antar hanya kalau semua item dicentang dan order belum selesai', () => {
    assert.equal(isReadyToDeliver(order({ servedCount: 2 })), true);
    assert.equal(isReadyToDeliver(order({ servedCount: 1 })), false);
    assert.equal(isReadyToDeliver(order({ servedCount: 2, kitchen: 'done' })), false);
    assert.equal(isReadyToDeliver(order({ servedCount: 0, servableCount: 0 })), false, 'order tanpa item yang bisa dibawa');
});

test('unservedCount tidak pernah negatif', () => {
    assert.equal(unservedCount(order({ servedCount: 1 })), 1);
    assert.equal(unservedCount(order({ servedCount: 5 })), 0);
});

test('papan mengurutkan yang masih berjalan dulu (terlama di atas), yang selesai di bawah', () => {
    const sorted = sortBoard([
        order({ code: 'ORD-DONE11', kitchen: 'done', paidAt: '2026-10-19T08:00:00+07:00' }),
        order({ code: 'ORD-NEW111', paidAt: '2026-10-19T10:30:00+07:00' }),
        order({ code: 'ORD-OLD111', kitchen: 'cooking', paidAt: '2026-10-19T10:05:00+07:00' }),
    ]);

    assert.deepEqual(sorted.map((o) => o.code), ['ORD-OLD111', 'ORD-NEW111', 'ORD-DONE11']);
});

test('sortBoard tidak mengubah array aslinya', () => {
    const original = [order({ code: 'ORD-BBBBBB', paidAt: '2026-10-19T11:00:00+07:00' }), order({ code: 'ORD-AAAAAA' })];
    const copy = [...original];

    sortBoard(original);

    assert.deepEqual(original, copy);
});

test('hitung dan saring per status', () => {
    const orders = [order({ kitchen: 'queued' }), order({ kitchen: 'queued', code: 'ORD-2' }), order({ kitchen: 'cooking', code: 'ORD-3' }), order({ kitchen: 'done', code: 'ORD-4' })];

    assert.deepEqual(countByStatus(orders), { all: 4, queued: 2, cooking: 1, done: 1 });
    assert.equal(filterOrders(orders, 'all').length, 4);
    assert.deepEqual(filterOrders(orders, 'cooking').map((o) => o.code), ['ORD-3']);
});

test('mergeOrder mengganti kartu yang sama atau menambah yang baru', () => {
    const base = [order({ code: 'ORD-1' }), order({ code: 'ORD-2' })];

    const replaced = mergeOrder(base, order({ code: 'ORD-2', kitchen: 'cooking' }));
    assert.equal(replaced.length, 2);
    assert.equal(replaced[1].kitchen, 'cooking');

    const added = mergeOrder(base, order({ code: 'ORD-3' }));
    assert.deepEqual(added.map((o) => o.code), ['ORD-1', 'ORD-2', 'ORD-3']);
});

test('peringatan sebelum menandai selesai hanya muncul kalau ada item belum dicentang', () => {
    assert.equal(completeWarning(order({ servedCount: 2 })), null);
    assert.match(completeWarning(order({ servedCount: 1 })), /1 item yang belum dicentang/);
});

test('peringatan item tidak tersedia menyebut nominal refund dan efek ke menu', () => {
    const message = unavailableWarning(order(), { name: 'Es Teh', lineTotal: 5000 }, formatRupiah);

    assert.match(message, /Es Teh/);
    assert.match(message, /refund Rp 5\.000/);
    assert.match(message, /menu otomatis jadi habis/);
});
