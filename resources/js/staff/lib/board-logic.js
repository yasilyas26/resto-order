// Logika papan karyawan yang murni (tanpa DOM/Alpine), supaya bisa diuji dengan `node --test`.

export const KITCHEN_LABEL = {
    queued: 'Belum dibuat',
    cooking: 'Sedang dibuat',
    done: 'Selesai',
};

/** Menit sejak order dibayar. Tidak pernah negatif (jam server dan perangkat bisa sedikit beda). */
export function waitMinutes(paidAtIso, nowMs) {
    const paid = Date.parse(paidAtIso);

    if (Number.isNaN(paid)) {
        return 0;
    }

    return Math.max(0, Math.floor((nowMs - paid) / 60000));
}

export function waitLabel(minutes) {
    if (minutes < 60) {
        return `${minutes} mnt`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest ? `${hours} j ${rest} mnt` : `${hours} j`;
}

/** Seberapa mendesak sebuah order: dipakai untuk mewarnai waktu tunggu. */
export function waitTone(minutes) {
    if (minutes >= 20) return 'late';
    if (minutes >= 10) return 'warn';

    return 'normal';
}

/** Semua item yang bisa dibawa sudah dicentang, tapi order belum ditandai selesai. */
export function isReadyToDeliver(order) {
    return order.kitchen !== 'done' && order.servableCount > 0 && order.servedCount === order.servableCount;
}

export function unservedCount(order) {
    return Math.max(0, order.servableCount - order.servedCount);
}

/** Yang masih berjalan di atas (terlama dulu, supaya yang menunggu paling lama terlihat duluan), yang selesai di bawah. */
export function sortBoard(orders) {
    const rank = (order) => (order.kitchen === 'done' ? 1 : 0);
    const paid = (order) => Date.parse(order.paidAt) || 0;

    return [...orders].sort((a, b) => rank(a) - rank(b) || paid(a) - paid(b) || a.code.localeCompare(b.code));
}

export function countByStatus(orders) {
    const counts = { all: orders.length, queued: 0, cooking: 0, done: 0 };

    for (const order of orders) {
        counts[order.kitchen] += 1;
    }

    return counts;
}

export function filterOrders(orders, filter) {
    return filter === 'all' ? orders : orders.filter((order) => order.kitchen === filter);
}

/** Ganti satu kartu dengan versi terbarunya (dari balasan aksi), atau tambahkan kalau belum ada. */
export function mergeOrder(orders, updated) {
    return orders.some((o) => o.code === updated.code)
        ? orders.map((o) => (o.code === updated.code ? updated : o))
        : [...orders, updated];
}

/** Pertanyaan konfirmasi sebelum tombol "Tandai Selesai", atau null kalau tidak perlu. */
export function completeWarning(order) {
    const left = unservedCount(order);

    return left > 0 ? `Masih ada ${left} item yang belum dicentang. Tandai order ini selesai?` : null;
}

export function unavailableWarning(order, item, formatRupiah) {
    return `Tandai "${item.name}" tidak tersedia? Order ini akan perlu refund ${formatRupiah(item.lineTotal)} dan menu otomatis jadi habis.`;
}
