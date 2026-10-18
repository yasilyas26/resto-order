// Logika keranjang yang murni (tanpa DOM/Alpine), supaya bisa diuji dengan `node --test`.
// Harga di sini hanya untuk tampilan: server menghitung ulang semuanya saat checkout.

export const NOTE_MAX = 200;
export const QTY_MAX = 50;

// Spasi setelah "Rp" sengaja non-breaking (U+00A0), supaya "Rp" dan angkanya tidak pernah terpisah di akhir baris.
export function formatRupiah(amount) {
    return 'Rp ' + Math.round(Number(amount) || 0).toLocaleString('id-ID');
}

export function clampQty(value) {
    const n = parseInt(value, 10);

    return Math.min(QTY_MAX, Math.max(1, Number.isNaN(n) ? 1 : n));
}

/** Pilihan terpilih sebagai daftar bertipe, urut mengikuti grup di menu. */
export function resolveSelection(menu, selectedIds) {
    const chosen = new Set(selectedIds);
    const result = [];

    for (const group of menu.groups) {
        for (const option of group.options) {
            if (chosen.has(option.id)) {
                result.push({
                    groupId: group.id,
                    groupName: group.name,
                    id: option.id,
                    name: option.name,
                    extra: Number(option.price) || 0,
                });
            }
        }
    }

    return result;
}

/** Galat per grup (kunci = id grup). Objek kosong berarti pilihan sudah sah. */
export function validateSelection(menu, selectedIds) {
    const errors = {};

    for (const group of menu.groups) {
        const count = group.options.filter((o) => selectedIds.includes(o.id)).length;

        if (count < group.min) {
            errors[group.id] =
                group.min === 1 && group.max === 1
                    ? `Pilih salah satu untuk ${group.name}.`
                    : `Pilih minimal ${group.min} untuk ${group.name}.`;
        } else if (count > group.max) {
            errors[group.id] = `Maksimal ${group.max} pilihan.`;
        }
    }

    return errors;
}

/**
 * Hasil setelah pengunjung mengetuk sebuah opsi.
 * - grup pilih-satu (max 1): mengganti pilihan; boleh dikosongkan hanya kalau tidak wajib
 * - grup pilih-banyak: menambah/mengurangi, dan menolak kalau sudah mencapai batas maksimal
 */
export function toggleOption(menu, selectedIds, groupId, optionId) {
    const group = menu.groups.find((g) => g.id === groupId);
    const option = group?.options.find((o) => o.id === optionId);

    if (!group || !option || !option.available) {
        return selectedIds;
    }

    const inGroup = new Set(group.options.map((o) => o.id));
    const current = selectedIds.filter((id) => inGroup.has(id));
    const others = selectedIds.filter((id) => !inGroup.has(id));
    const has = current.includes(optionId);

    if (group.max === 1) {
        if (has) {
            return group.min === 0 ? others : selectedIds;
        }

        return [...others, optionId];
    }

    if (has) {
        return [...others, ...current.filter((id) => id !== optionId)];
    }

    if (current.length >= group.max) {
        return selectedIds;
    }

    return [...others, ...current, optionId];
}

/** Grup wajib pilih-satu langsung terisi opsi tersedia pertama (biasanya yang paling ringan). */
export function defaultSelection(menu) {
    const ids = [];

    for (const group of menu.groups) {
        if (group.min >= 1 && group.max === 1) {
            const first = group.options.find((o) => o.available);

            if (first) {
                ids.push(first.id);
            }
        }
    }

    return ids;
}

/** Dua item dengan menu, opsi, dan catatan yang sama dianggap satu baris (jumlahnya digabung). */
export function itemKey(menuId, optionIds, note) {
    const ids = [...optionIds].sort((a, b) => a - b).join(',');

    return `${menuId}|${ids}|${(note || '').trim().toLowerCase()}`;
}

export function buildCartItem(menu, selectedIds, qty, note) {
    const options = resolveSelection(menu, selectedIds);
    const cleanNote = (note || '').trim().slice(0, NOTE_MAX);

    return {
        key: itemKey(menu.id, options.map((o) => o.id), cleanNote),
        menuId: menu.id,
        name: menu.name,
        image: menu.image,
        category: menu.category ?? null,
        price: Number(menu.price) || 0,
        options,
        note: cleanNote,
        qty: clampQty(qty),
    };
}

export function unitTotal(item) {
    return item.price + item.options.reduce((sum, o) => sum + o.extra, 0);
}

export function lineTotal(item) {
    return unitTotal(item) * item.qty;
}

export function cartSubtotal(items) {
    return items.reduce((sum, item) => sum + lineTotal(item), 0);
}

export function cartCount(items) {
    return items.reduce((sum, item) => sum + item.qty, 0);
}

export function addOrMerge(items, newItem) {
    const existing = items.find((i) => i.key === newItem.key);

    if (!existing) {
        return [...items, newItem];
    }

    return items.map((i) => (i.key === newItem.key ? { ...i, qty: clampQty(i.qty + newItem.qty) } : i));
}

export function replaceItem(items, oldKey, newItem) {
    return addOrMerge(items.filter((i) => i.key !== oldKey), newItem);
}

export function setQuantity(items, key, qty) {
    return items.map((i) => (i.key === key ? { ...i, qty: clampQty(qty) } : i));
}

/** Bentuk yang dikirim ke server: hanya ID, jumlah, dan catatan. Harga sengaja tidak ikut. */
export function toPayload(items) {
    return items.map((item) => ({
        menu_id: item.menuId,
        qty: item.qty,
        note: item.note || null,
        options: item.options.map((o) => o.id),
    }));
}

/** Membaca keranjang dari localStorage dengan curiga: buang apa pun yang bentuknya tidak sesuai. */
export function sanitizeStoredCart(raw) {
    if (!Array.isArray(raw)) {
        return [];
    }

    const items = [];

    for (const entry of raw) {
        if (!entry || !Number.isInteger(entry.menuId) || typeof entry.name !== 'string' || !Array.isArray(entry.options)) {
            continue;
        }

        const options = entry.options
            .filter((o) => o && Number.isInteger(o.id) && typeof o.name === 'string')
            .map((o) => ({
                groupId: Number(o.groupId) || 0,
                groupName: String(o.groupName ?? ''),
                id: o.id,
                name: o.name,
                extra: Number(o.extra) || 0,
            }));

        const note = typeof entry.note === 'string' ? entry.note.slice(0, NOTE_MAX) : '';

        items.push({
            key: itemKey(entry.menuId, options.map((o) => o.id), note),
            menuId: entry.menuId,
            name: entry.name,
            image: typeof entry.image === 'string' ? entry.image : null,
            category: typeof entry.category === 'string' ? entry.category : null,
            price: Number(entry.price) || 0,
            options,
            note,
            qty: clampQty(entry.qty),
        });
    }

    return items;
}

/**
 * Mencocokkan keranjang lama dengan menu terbaru: menu yang sudah dihapus/habis atau opsi yang
 * sudah tidak sah dibuang, sisanya dimutakhirkan (nama, foto, harga). Mengembalikan item yang tersisa
 * dan nama-nama yang dibuang, untuk diberitahukan ke pengunjung.
 */
export function reconcileCart(items, menus) {
    const kept = [];
    const removed = [];

    for (const item of items) {
        const menu = menus[item.menuId];

        if (!menu || !menu.available) {
            removed.push(item.name);
            continue;
        }

        const ids = item.options.map((o) => o.id);
        const known = new Set(menu.groups.flatMap((g) => g.options.filter((o) => o.available).map((o) => o.id)));

        if (!ids.every((id) => known.has(id)) || Object.keys(validateSelection(menu, ids)).length > 0) {
            removed.push(item.name);
            continue;
        }

        kept.push(buildCartItem(menu, ids, item.qty, item.note));
    }

    // Setelah dimutakhirkan, dua baris bisa jadi identik: gabungkan.
    return { items: kept.reduce((acc, item) => addOrMerge(acc, item), []), removed };
}
