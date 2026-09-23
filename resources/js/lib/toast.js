import { reactive } from 'vue';

export const toasts = reactive([]);

export function toast(message, { tone = 'success', description = null, duration = 3800 } = {}) {
    const t = { id: crypto.randomUUID?.() ?? `${Date.now()}${Math.random()}`, message, tone, description };
    toasts.push(t);
    setTimeout(() => dismiss(t.id), duration);
}

export const toastError = (e) => toast(e?.first ?? e?.message ?? String(e), { tone: 'danger' });

export function dismiss(id) {
    const i = toasts.findIndex((t) => t.id === id);
    if (i !== -1) toasts.splice(i, 1);
}
