// Vékony fetch-réteg: JSON, hibák egységes kezelése, magyar hibaüzenetek.
export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function api(path, { method = 'GET', body } = {}) {
    const res = await fetch(`/api${path}`, {
        method,
        headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (!res.ok) {
        throw new ApiError(data?.message ?? 'Váratlan hiba történt.', res.status, data?.errors ?? {});
    }

    return data;
}

const huf = new Intl.NumberFormat('hu-HU', { style: 'currency', currency: 'HUF', maximumFractionDigits: 0 });
const dateFmt = new Intl.DateTimeFormat('hu-HU', { month: 'short', day: 'numeric' });
const dateTimeFmt = new Intl.DateTimeFormat('hu-HU', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

export const money = (v) => huf.format(v ?? 0);
export const date = (v) => (v ? dateFmt.format(new Date(v)) : '–');
export const dateTime = (v) => (v ? dateTimeFmt.format(new Date(v)) : '–');
