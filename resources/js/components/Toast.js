import { reactive } from 'vue';

export const toasts = reactive([]);

export function toast(message, tone = 'ok') {
    const t = { id: Date.now() + Math.random(), message, tone };
    toasts.push(t);
    setTimeout(() => toasts.splice(toasts.indexOf(t), 1), 3500);
}
