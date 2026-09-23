import { reactive } from 'vue';
import { api } from './api.js';

// Oldalsáv-számlálók: bármely művelet után frissíthető, hogy a navigáció mindig igazat mutasson.
export const badges = reactive({ pending: 0, lowStock: 0, openTasks: 0, overdueTasks: 0, invalidInvoices: 0 });

export async function refreshBadges() {
    try {
        const d = (await api('/dashboard?days=7')).data;
        Object.assign(badges, {
            pending: d.orders_by_status.pending,
            lowStock: d.low_stock_count,
            openTasks: d.open_tasks,
            overdueTasks: d.overdue_tasks,
        });
    } catch {
        // a számlálók nem kritikusak
    }
}
