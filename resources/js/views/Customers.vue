<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api, date, dateTime, money } from '../api.js';
import { toast } from '../components/Toast.js';

const props = defineProps({ focus: Object });
const customers = ref([]);
const search = ref('');
const selected = ref(null);
const summary = ref(null);
const summaryLoading = ref(false);
const orders = ref([]);
const entry = reactive({ type: 'call', subject: '', body: '', due_at: '' });
const entryError = ref('');

async function load() {
    const q = new URLSearchParams({ per_page: 100, ...(search.value ? { search: search.value } : {}) });
    customers.value = (await api(`/crm/customers?${q}`)).data;
    if (!selected.value && customers.value.length) open(customers.value.find((c) => c.id === props.focus?.customerId) ?? customers.value[0]);
}

async function open(c) {
    summary.value = null;
    const [detail, o] = await Promise.all([api(`/crm/customers/${c.id}`), api(`/orders?customer_id=${c.id}&per_page=5`)]);
    selected.value = detail.data;
    orders.value = o.data;
    loadSummary();
}

async function loadSummary() {
    summaryLoading.value = true;
    try {
        summary.value = (await api(`/crm/customers/${selected.value.id}/summary`)).data;
    } finally {
        summaryLoading.value = false;
    }
}

async function addEntry() {
    entryError.value = '';
    try {
        await api(`/crm/customers/${selected.value.id}/interactions`, {
            method: 'POST',
            body: { type: entry.type, subject: entry.subject, body: entry.body || null, due_at: entry.type === 'task' ? entry.due_at : null },
        });
        Object.assign(entry, { subject: '', body: '', due_at: '' });
        toast('Bejegyzés mentve');
        await open(selected.value);
        load();
    } catch (e) {
        entryError.value = Object.values(e.errors).flat()[0] ?? e.message;
    }
}

async function complete(i) {
    await api(`/crm/customers/${selected.value.id}/interactions/${i.id}/complete`, { method: 'PATCH' });
    toast('Teendő lezárva');
    await open(selected.value);
    load();
}

const icon = { call: '☎', email: '✉', meeting: '◎', note: '✎', task: '☐', order: '▣' };

let t;
watch(search, () => { clearTimeout(t); t = setTimeout(load, 250); });
onMounted(load);
</script>

<template>
    <header class="mb-5">
        <h1 class="text-xl font-semibold">Ügyfelek</h1>
        <p class="text-sm text-slate-500">Idővonal, teendők és AI-összefoglaló. A rendelések automatikusan megjelennek az idővonalon.</p>
    </header>

    <div class="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 p-3"><input v-model="search" type="search" class="input" placeholder="Keresés…" aria-label="Ügyfél keresése" /></div>
            <ul class="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                <li v-for="c in customers" :key="c.id">
                    <button class="w-full px-4 py-3 text-left hover:bg-slate-50" :class="selected?.id === c.id ? 'bg-brand-50' : ''" @click="open(c)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium">{{ c.company }}</span>
                            <span v-if="c.open_tasks_count" class="num rounded-full bg-amber-100 px-1.5 text-xs font-medium text-amber-700">{{ c.open_tasks_count }}</span>
                        </div>
                        <div class="truncate text-xs text-slate-500">{{ c.name }} · {{ c.city }}</div>
                    </button>
                </li>
            </ul>
        </div>

        <div v-if="selected" class="space-y-5">
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold">{{ selected.company }}</h2>
                        <div class="text-sm text-slate-500">{{ selected.name }} · {{ selected.email }} · {{ selected.phone }}</div>
                        <div v-if="selected.tax_number" class="text-xs text-slate-400">Adószám: {{ selected.tax_number }} · {{ selected.postal_code }} {{ selected.city }}, {{ selected.address }}</div>
                    </div>
                    <div class="text-right text-xs text-slate-500">Utolsó kapcsolat<br /><span class="text-sm font-medium text-slate-700">{{ date(selected.last_contacted_at) }}</span></div>
                </div>

                <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50/60 p-4">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-semibold tracking-wide text-brand-700 uppercase">Összefoglaló</span>
                        <span v-if="summary" class="rounded bg-white px-1.5 py-0.5 text-[11px] text-slate-500 ring-1 ring-slate-200">{{ summary.source === 'ai' ? 'AI · Laravel AI SDK' : 'szabályalapú' }}</span>
                    </div>
                    <p class="text-sm leading-relaxed">{{ summaryLoading ? 'Összefoglaló készül…' : summary?.summary }}</p>
                    <p v-if="summary?.next_action" class="mt-2 text-sm"><span class="font-semibold text-brand-700">Következő lépés:</span> {{ summary.next_action }}</p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <div class="card p-5">
                    <h3 class="mb-3 text-sm font-semibold">Idővonal</h3>
                    <form class="mb-4 space-y-2" @submit.prevent="addEntry">
                        <div class="flex gap-2">
                            <select v-model="entry.type" class="input w-36" aria-label="Bejegyzés típusa">
                                <option value="call">Hívás</option><option value="email">E-mail</option><option value="meeting">Találkozó</option><option value="note">Jegyzet</option><option value="task">Teendő</option>
                            </select>
                            <input v-model="entry.subject" class="input" placeholder="Tárgy" aria-label="Tárgy" required />
                        </div>
                        <div class="flex gap-2">
                            <input v-if="entry.type === 'task'" v-model="entry.due_at" type="datetime-local" class="input" aria-label="Határidő" required />
                            <input v-else v-model="entry.body" class="input" placeholder="Részletek (opcionális)" aria-label="Részletek" />
                            <button class="btn-primary shrink-0">Mentés</button>
                        </div>
                        <p v-if="entryError" class="text-sm text-red-600">{{ entryError }}</p>
                    </form>
                    <ol class="space-y-3">
                        <li v-for="i in selected.interactions" :key="i.id" class="flex gap-3">
                            <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-sm" :class="i.type === 'task' ? (i.completed_at ? 'bg-emerald-50 text-emerald-600' : i.is_overdue ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600') : 'bg-slate-100 text-slate-500'" aria-hidden="true">{{ i.type === 'task' && i.completed_at ? '✓' : icon[i.type] }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-sm font-medium" :class="i.completed_at ? 'text-slate-400 line-through' : ''">{{ i.subject }}</span>
                                    <span class="shrink-0 text-xs text-slate-400">{{ dateTime(i.occurred_at) }}</span>
                                </div>
                                <p v-if="i.body" class="text-sm text-slate-500">{{ i.body }}</p>
                                <div v-if="i.type === 'task' && !i.completed_at" class="mt-1 flex items-center gap-3 text-xs">
                                    <span :class="i.is_overdue ? 'font-medium text-red-600' : 'text-slate-500'">Határidő: {{ dateTime(i.due_at) }}</span>
                                    <button class="font-medium text-brand-600" @click="complete(i)">Kész</button>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>

                <div class="card self-start p-5">
                    <h3 class="mb-3 text-sm font-semibold">Legutóbbi rendelések</h3>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li v-for="o in orders" :key="o.id" class="flex justify-between py-2">
                            <span><span class="font-mono text-xs">{{ o.number }}</span> <span class="text-slate-500">· {{ o.status_label }}</span></span>
                            <span class="num font-medium">{{ money(o.total) }}</span>
                        </li>
                        <li v-if="!orders.length" class="py-4 text-center text-slate-400">Még nem rendelt.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
