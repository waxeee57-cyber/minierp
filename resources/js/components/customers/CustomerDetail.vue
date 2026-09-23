<script setup>
import { reactive, ref, watch } from 'vue';
import { AlarmClock, ArrowLeft, Mail, MapPin, Pencil, Phone, Plus, RefreshCw, Sparkles, StickyNote, Users } from 'lucide-vue-next';
import CustomerFormDrawer from './CustomerFormDrawer.vue';
import Timeline from './Timeline.vue';
import Avatar from '../ui/Avatar.vue';
import Button from '../ui/Button.vue';
import Empty from '../ui/Empty.vue';
import Segmented from '../ui/Segmented.vue';
import StatusBadge from '../ui/StatusBadge.vue';
import { api } from '../../lib/api.js';
import { refreshBadges } from '../../lib/badges.js';
import { compactMoney, money, relative } from '../../lib/format.js';
import { toast, toastError } from '../../lib/toast.js';

const props = defineProps({ id: [String, Number] });
const emit = defineEmits(['changed', 'back']);
const c = ref(null);
const stats = ref(null);
const orders = ref([]);
const summary = ref(null);
const summaryState = ref('idle');
const editing = ref(false);
const entry = reactive({ type: 'call', subject: '', body: '', due_at: '' });
const entryError = ref('');
const saving = ref(false);

async function load({ quiet = false } = {}) {
    if (!quiet) c.value = null;
    try {
        const [detail, o] = await Promise.all([api(`/crm/customers/${props.id}`), api(`/orders?customer_id=${props.id}&per_page=6`)]);
        c.value = detail.data;
        stats.value = detail.meta.stats;
        orders.value = o.data;
        loadSummary();
    } catch (e) {
        toastError(e);
    }
}

async function loadSummary() {
    summaryState.value = 'loading';
    try {
        summary.value = (await api(`/crm/customers/${props.id}/summary`)).data;
        summaryState.value = 'ok';
    } catch (e) {
        summaryState.value = e.status === 429 ? 'throttled' : 'error';
    }
}

watch(() => props.id, () => load(), { immediate: true });

async function addEntry() {
    entryError.value = '';
    saving.value = true;
    try {
        await api(`/crm/customers/${c.value.id}/interactions`, {
            method: 'POST',
            body: { type: entry.type, subject: entry.subject, body: entry.type === 'task' ? null : entry.body || null, due_at: entry.type === 'task' ? entry.due_at : null },
        });
        toast(entry.type === 'task' ? 'Teendő létrehozva' : 'Bejegyzés mentve');
        Object.assign(entry, { subject: '', body: '', due_at: '' });
        await load({ quiet: true });
        emit('changed');
        refreshBadges();
    } catch (e) {
        entryError.value = e.first;
    } finally {
        saving.value = false;
    }
}

async function complete(i) {
    try {
        await api(`/crm/customers/${c.value.id}/interactions/${i.id}/complete`, { method: 'PATCH' });
        toast('Teendő lezárva');
        await load({ quiet: true });
        emit('changed');
        refreshBadges();
    } catch (e) {
        toastError(e);
    }
}

function onSaved() {
    editing.value = false;
    load({ quiet: true });
    emit('changed');
}

const types = [
    { value: 'call', label: 'Hívás', icon: Phone },
    { value: 'email', label: 'E-mail', icon: Mail },
    { value: 'meeting', label: 'Találkozó', icon: Users },
    { value: 'note', label: 'Jegyzet', icon: StickyNote },
    { value: 'task', label: 'Teendő', icon: AlarmClock },
];
</script>

<template>
    <div v-if="!c" class="space-y-4"><div class="skeleton h-40" /><div class="skeleton h-28" /><div class="skeleton h-72" /></div>

    <div v-else class="space-y-5">
        <button class="flex items-center gap-1.5 text-sm font-medium text-muted lg:hidden" @click="emit('back')"><ArrowLeft class="size-4" />Ügyfelek</button>

        <!-- Fejléc -->
        <section class="card p-4 sm:p-5">
            <div class="flex flex-wrap items-start gap-4">
                <Avatar :name="c.company ?? c.name" size="lg" />
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-semibold tracking-tight">{{ c.company ?? c.name }}</h2>
                    <p class="text-sm text-muted">{{ c.company ? c.name : 'Magánszemély' }}</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-subtle">
                        <a v-if="c.email" :href="`mailto:${c.email}`" class="flex min-w-0 items-center gap-1.5 break-all hover:text-accent"><Mail class="size-3.5" />{{ c.email }}</a>
                        <a v-if="c.phone" :href="`tel:${c.phone.replace(/\s/g, '')}`" class="flex items-center gap-1.5 hover:text-accent"><Phone class="size-3.5" />{{ c.phone }}</a>
                        <span v-if="c.city" class="flex min-w-0 items-center gap-1.5"><MapPin class="size-3.5" />{{ [c.postal_code, c.city].filter(Boolean).join(' ') }}{{ c.address ? `, ${c.address}` : '' }}</span>
                    </div>
                    <p v-if="c.tax_number" class="num mt-1 text-xs text-subtle">Adószám: {{ c.tax_number }}</p>
                </div>
                <div class="flex w-full gap-2 sm:w-auto">
                    <Button size="sm" class="flex-1 sm:flex-none" @click="editing = true"><Pencil />Szerkesztés</Button>
                    <Button size="sm" variant="primary" class="flex-1 sm:flex-none" :to="{ path: '/orders/new', query: { customer: c.id } }"><Plus />Új rendelés</Button>
                </div>
            </div>

            <dl v-if="stats" class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-4">
                <div class="bg-surface p-3"><dt class="text-xs text-subtle">Rendelések</dt><dd class="num mt-0.5 text-lg font-semibold">{{ stats.orders }}</dd></div>
                <div class="bg-surface p-3"><dt class="text-xs text-subtle">Összes bevétel</dt><dd class="num mt-0.5 text-lg font-semibold">{{ compactMoney(stats.revenue) }}</dd></div>
                <div class="bg-surface p-3"><dt class="text-xs text-subtle">Átlagos kosár</dt><dd class="num mt-0.5 text-lg font-semibold">{{ compactMoney(stats.average_order) }}</dd></div>
                <div class="bg-surface p-3"><dt class="text-xs text-subtle">Utolsó rendelés</dt><dd class="mt-0.5 text-lg font-semibold">{{ stats.last_order_at ? relative(stats.last_order_at) : 'még nincs' }}</dd></div>
            </dl>

            <!-- Összefoglaló -->
            <div class="mt-4 rounded-xl border border-line bg-[color-mix(in_oklab,var(--accent)_5%,var(--surface))] p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-accent uppercase"><Sparkles class="size-3.5" />Helyzetkép</span>
                    <div class="flex items-center gap-2">
                        <span v-if="summary" class="rounded-md px-1.5 py-0.5 text-[11px] font-medium" :class="summary.source === 'ai' ? 'soft-accent' : 'soft-neutral'">{{ summary.source === 'ai' ? 'AI · Laravel AI SDK' : 'szabályalapú' }}</span>
                        <button class="grid size-6 place-items-center rounded-md text-subtle hover:bg-surface-2 hover:text-fg" aria-label="Összefoglaló frissítése" @click="loadSummary"><RefreshCw class="size-3.5" :class="summaryState === 'loading' ? 'animate-spin' : ''" /></button>
                    </div>
                </div>
                <div v-if="summaryState === 'loading' && !summary" class="space-y-2"><div class="skeleton h-3 w-full" /><div class="skeleton h-3 w-2/3" /></div>
                <template v-else-if="summary">
                    <p class="text-sm leading-relaxed">{{ summary.summary }}</p>
                    <p v-if="summary.next_action" class="mt-2 text-sm"><span class="font-semibold text-accent">Következő lépés:</span> {{ summary.next_action }}</p>
                </template>
                <p v-else class="text-sm text-subtle">{{ summaryState === 'throttled' ? 'Túl sok kérés – pár másodperc múlva frissítsd.' : 'Az összefoglaló most nem elérhető.' }}</p>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <!-- Idővonal -->
            <section class="card p-4 sm:p-5">
                <h3 class="mb-3 text-[15px] font-semibold">Idővonal</h3>
                <form class="mb-5 space-y-2.5 rounded-xl border border-line p-3" @submit.prevent="addEntry">
                    <Segmented v-model="entry.type" :options="types" label="Bejegyzés típusa" wrap />
                    <input v-model="entry.subject" class="field" :placeholder="entry.type === 'task' ? 'Mit kell tenni? pl. Árajánlat küldése' : 'Tárgy'" aria-label="Tárgy" required maxlength="160" />
                    <div class="flex gap-2">
                        <input v-if="entry.type === 'task'" v-model="entry.due_at" type="datetime-local" class="field" aria-label="Határidő" required />
                        <input v-else v-model="entry.body" class="field" placeholder="Részletek (opcionális)" aria-label="Részletek" maxlength="5000" />
                        <Button type="submit" variant="primary" :loading="saving">Mentés</Button>
                    </div>
                    <p v-if="entryError" class="text-sm text-danger" role="alert">{{ entryError }}</p>
                </form>
                <Timeline v-if="c.interactions.length" :items="c.interactions" @complete="complete" />
                <Empty v-else title="Még üres az idővonal" text="Az első rendelés és minden állapotváltás automatikusan ide kerül." />
            </section>

            <!-- Rendelések -->
            <section class="card self-start overflow-hidden">
                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                    <h3 class="text-[15px] font-semibold">Rendelések</h3>
                    <RouterLink v-if="orders.length" :to="{ path: '/orders', query: { q: c.company ?? c.name } }" class="text-[13px] font-medium text-accent hover:underline">Összes</RouterLink>
                </div>
                <ul v-if="orders.length" class="divide-y divide-line">
                    <li v-for="o in orders" :key="o.id">
                        <RouterLink :to="`/orders/${o.id}`" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-surface-2/60">
                            <div class="min-w-0 flex-1">
                                <div class="font-mono text-[13px]">{{ o.number }}</div>
                                <div class="text-xs text-subtle">{{ relative(o.placed_at) }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="num text-sm font-semibold">{{ money(o.total) }}</span>
                                <StatusBadge :status="o.status" :label="o.status_label" />
                            </div>
                        </RouterLink>
                    </li>
                </ul>
                <Empty v-else title="Még nem rendelt" />
            </section>
        </div>

        <CustomerFormDrawer v-if="editing" :customer="c" @close="editing = false" @saved="onSaved" />
    </div>
</template>
