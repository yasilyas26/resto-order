/** fetch() ke server Laravel dengan token CSRF dan balasan JSON. */
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
        // balasan bukan JSON (mis. halaman error); data dibiarkan kosong
    }

    return { ok: response.ok, status: response.status, data };
}

/** Daftar pesan galat dari balasan validasi Laravel, atau pesan cadangan. */
export function errorMessages(data, fallback) {
    if (data?.errors) {
        const all = Object.values(data.errors).flat();

        if (all.length) {
            return all;
        }
    }

    return [data?.message || fallback];
}
