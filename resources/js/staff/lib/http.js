/** fetch() ke server Laravel dengan token CSRF dan balasan JSON (sama dengan sisi pelanggan, entri JS terpisah). */
export async function postJson(url, body = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    return toResult(response);
}

export async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    return toResult(response);
}

async function toResult(response) {
    let data = {};

    try {
        data = await response.json();
    } catch {
        // balasan bukan JSON (mis. sesi habis lalu dialihkan ke halaman login)
    }

    // Sesi karyawan habis: kembali ke portal, bukan diam-diam gagal terus.
    if (response.status === 401 || response.status === 419) {
        window.location.href = '/portal';
    }

    return { ok: response.ok, status: response.status, data };
}
