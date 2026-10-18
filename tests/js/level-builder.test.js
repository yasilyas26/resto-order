import test from 'node:test';
import assert from 'node:assert/strict';

import levelBuilder, { MAX_LEVELS, describeError, levelRange } from '../../resources/js/admin/levelBuilder.js';

const base = { prefix: 'Level', from: 0, to: 8, surchargeFrom: '', surcharge: '' };

test('levelRange membuat Level 0 sampai Level 8', () => {
    const levels = levelRange(base);

    assert.equal(levels.length, 9);
    assert.deepEqual(levels.map((l) => l.name), ['Level 0', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8']);
    assert.ok(levels.every((l) => l.extra === 0));
});

test('tambahan harga berlaku mulai level yang dipilih ke atas', () => {
    const levels = levelRange({ ...base, surchargeFrom: '3', surcharge: '1000' });

    assert.deepEqual(levels.map((l) => l.extra), [0, 0, 0, 1000, 1000, 1000, 1000, 1000, 1000]);
});

test('tambahan harga kosong atau tanpa level awal berarti gratis semua', () => {
    assert.ok(levelRange({ ...base, surchargeFrom: '', surcharge: '1000' }).every((l) => l.extra === 0));
    assert.ok(levelRange({ ...base, surchargeFrom: '3', surcharge: '' }).every((l) => l.extra === 0));
});

test('isian dari form (teks) tetap dipahami', () => {
    assert.equal(levelRange({ ...base, from: '2', to: '4' }).length, 3);
    assert.equal(levelRange({ ...base, prefix: '  Ukuran  ', from: 1, to: 1 })[0].name, 'Ukuran 1');
});

test('rentang yang tidak sah menghasilkan daftar kosong', () => {
    assert.deepEqual(levelRange({ ...base, from: 5, to: 2 }), []);
    assert.deepEqual(levelRange({ ...base, from: '', to: 8 }), []);
    assert.deepEqual(levelRange({ ...base, from: 'a', to: 8 }), []);
    assert.deepEqual(levelRange({ ...base, from: -1, to: 3 }), []);
});

test('batas jumlah level sama dengan server', () => {
    assert.equal(MAX_LEVELS, 21);
    assert.equal(levelRange({ ...base, from: 0, to: 20 }).length, 21);
    assert.deepEqual(levelRange({ ...base, from: 0, to: 21 }), []);
});

test('describeError menjelaskan masalahnya dengan bahasa yang jelas', () => {
    assert.equal(describeError({ from: 0, to: 8 }), '');
    assert.match(describeError({ from: 5, to: 2 }), /tidak boleh lebih kecil/);
    assert.match(describeError({ from: '', to: 2 }), /Isi level/);
    assert.match(describeError({ from: 0, to: 30 }), /Maksimal 21/);
});

test('komponen Alpine: pratinjau hanya memunculkan masalah kalau fitur dinyalakan', () => {
    const off = levelBuilder({ ...base, enabled: false, from: 5, to: 2 });
    const on = levelBuilder({ ...base, enabled: true, from: 5, to: 2 });

    assert.equal(off.problem, '');
    assert.match(on.problem, /tidak boleh lebih kecil/);
    assert.equal(levelBuilder({ ...base, enabled: true }).levels.length, 9);
});

test('label harga', () => {
    const builder = levelBuilder({ ...base, enabled: true });

    assert.equal(builder.price(0), 'gratis');
    assert.match(builder.price(1000), /^\+Rp 1\.000$/);
});
