// Hitung mundur batas bayar. Selisih jam server dan jam perangkat dikoreksi, supaya jam HP yang salah
// tidak membuat hitungannya meleset; yang menentukan kedaluwarsa tetap server.

/** Selisih (ms) antara jam server dan jam perangkat, dari timestamp ISO yang dikirim server. */
export function offsetFrom(serverTimeIso) {
    const server = Date.parse(serverTimeIso);

    return Number.isNaN(server) ? 0 : server - Date.now();
}

/** Sisa detik sampai batas (tidak pernah negatif). Null kalau tidak ada batas. */
export function secondsLeft(expiresAtIso, offsetMs = 0) {
    if (!expiresAtIso) {
        return null;
    }

    const deadline = Date.parse(expiresAtIso);

    if (Number.isNaN(deadline)) {
        return null;
    }

    return Math.max(0, Math.ceil((deadline - (Date.now() + offsetMs)) / 1000));
}

/** 4:05 -> "04:05" */
export function formatClock(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
