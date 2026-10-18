// Pembuat deret level di form menu admin (mis. Level 0 sampai Level 8).
// Aturan batasnya sama dengan di server (LevelGroupBuilder::MAX_LEVELS); server tetap yang memutuskan.

export const MAX_LEVELS = 21;

/**
 * Daftar level yang akan dibuat, untuk pratinjau. Kosong kalau isiannya belum sah.
 * Tambahan harga berlaku mulai level `surchargeFrom` ke atas.
 */
export function levelRange({ prefix, from, to, surchargeFrom, surcharge }) {
    const start = parseInt(from, 10);
    const end = parseInt(to, 10);

    if (Number.isNaN(start) || Number.isNaN(end) || start < 0 || end < start || end - start + 1 > MAX_LEVELS) {
        return [];
    }

    const threshold = surchargeFrom === '' || surchargeFrom === null ? null : parseInt(surchargeFrom, 10);
    const extra = parseInt(surcharge, 10) || 0;
    const levels = [];

    for (let level = start; level <= end; level++) {
        levels.push({
            name: `${String(prefix ?? '').trim()} ${level}`.trim(),
            extra: threshold !== null && !Number.isNaN(threshold) && level >= threshold ? extra : 0,
        });
    }

    return levels;
}

export function describeError({ from, to }) {
    const start = parseInt(from, 10);
    const end = parseInt(to, 10);

    if (Number.isNaN(start) || Number.isNaN(end)) return 'Isi level awal dan akhir.';
    if (end < start) return 'Level akhir tidak boleh lebih kecil dari level awal.';
    if (end - start + 1 > MAX_LEVELS) return `Maksimal ${MAX_LEVELS} level sekali buat.`;

    return '';
}

export default function levelBuilder(initial) {
    return {
        ...initial,

        get levels() {
            return levelRange(this);
        },

        get problem() {
            return this.enabled ? describeError(this) : '';
        },

        price(extra) {
            return extra > 0 ? `+Rp ${extra.toLocaleString('id-ID')}` : 'gratis';
        },
    };
}
