// Vékony fetch-réteg: JSON, egységes hibakezelés, magyar hibaüzenetek, megszakítható kérések.
export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }

    /** Az első mezőhiba vagy az általános üzenet – űrlapokhoz. */
    get first() {
        return Object.values(this.errors).flat()[0] ?? this.message;
    }
}

export async function api(path, { method = 'GET', body, signal } = {}) {
    let res;
    try {
        res = await fetch(`/api${path}`, {
            method,
            signal,
            headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch (e) {
        if (e.name === 'AbortError') throw e;
        throw new ApiError('Nincs kapcsolat a szerverrel. Ellenőrizd a hálózatot.', 0);
    }

    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (!res.ok) {
        const fallback = {
            404: 'A keresett elem nem található.',
            419: 'A munkamenet lejárt, frissítsd az oldalt.',
            429: 'Túl sok kérés egyszerre – várj pár másodpercet.',
        }[res.status] ?? 'Váratlan hiba történt.';
        throw new ApiError(res.status === 422 ? data?.message ?? fallback : fallback, res.status, data?.errors ?? {});
    }

    return data;
}

export async function apiText(path) {
    const res = await fetch(`/api${path}`);
    if (!res.ok) throw new ApiError('Nem sikerült betölteni.', res.status);
    return res.text();
}
