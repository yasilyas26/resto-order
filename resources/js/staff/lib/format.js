// Spasi setelah "Rp" sengaja non-breaking (U+00A0), supaya "Rp" dan angkanya tidak pernah terpisah di akhir baris.
export function formatRupiah(amount) {
    return 'Rp ' + Math.round(Number(amount) || 0).toLocaleString('id-ID');
}
