// localStorage bisa melempar galat (mode privat, data situs diblokir), jadi semua akses dibungkus.

export function readJson(key, fallback = null) {
    try {
        const raw = window.localStorage.getItem(key);

        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function writeJson(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // abaikan: halaman tetap jalan tanpa penyimpanan
    }
}

const ORDERS_KEY = 'resto.orders.v1';
const MAX_REMEMBERED = 30;

/** Kode order yang pernah dibuat dari perangkat ini (untuk riwayat tamu). */
export function getOrderCodes() {
    const codes = readJson(ORDERS_KEY, []);

    return Array.isArray(codes) ? codes.filter((c) => typeof c === 'string') : [];
}

const DRAFT_KEY = 'resto.draftOrder.v1';

/** Kode order yang sudah dibuat dari keranjang tapi belum dibayar (pembayarannya belum dimulai). */
export function getDraftOrder() {
    const code = readJson(DRAFT_KEY, null);

    return typeof code === 'string' ? code : null;
}

export function setDraftOrder(code) {
    writeJson(DRAFT_KEY, code);
}

export function clearDraftOrder() {
    try {
        window.localStorage.removeItem(DRAFT_KEY);
    } catch {
        // abaikan
    }
}

export function rememberOrder(code) {
    const codes = [code, ...getOrderCodes().filter((c) => c !== code)].slice(0, MAX_REMEMBERED);

    writeJson(ORDERS_KEY, codes);
}
