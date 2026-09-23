<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, Inbox, Plus, Search } from 'lucide-vue-next';
import NewOrderDrawer from '../components/orders/NewOrderDrawer.vue';
import OrderDrawer from '../components/orders/OrderDrawer.vue';
import PageHeader from '../components/PageHeader.vue';
import Avatar from '../components/ui/Avatar.vue';
import Button from '../components/ui/Button.vue';
import Empty from '../components/ui/Empty.vue';
import Kbd from '../components/ui/Kbd.vue';
import Segmented from '../components/ui/Segmented.vue';
import SkeletonRows from '../components/ui/SkeletonRows.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { api } from '../lib/api.js';
import { money, relative } from '../lib/format.js';
import { toastError } from '../lib/toast.js';

const route = useRoute();
const router = useRouter();
const orders = ref(null);
const meta = ref(null);
const search = ref(String(route.query.q ?? ''));
let ctrl;

const status = computed({
    get: () => String(route.query.status ?? ''),
    set: (v) => router.replace({ query: { ...route.query, status: v || undefined, page: undefined } }),
});
const page = computed(() => Number(route.query.page ?? 1));

async function load() {
    ctrl?.abort();
    ctrl = new AbortController();
    const q = new URLSearchParams({ page: page.value, per_page: 15, ...(status.value && { status: status.value }), ...(search.value.trim() && { search: search.value.trim() }) });
    try {
        const res = await api(`/orders?${q}`, { signal: ctrl.signal });
        orders.value = res.data;
        meta.value = res.meta;
    } catch (e) {
        if (e.name !== 'AbortError') toastError(e);
    }
}

watch(() => [route.query.status, route.query.page], load, { immediate: true });
let t;
watch(search, (v) => {
    clearTimeout(t);
    t = setTimeout(() => {
        router.replace({ query: { ...route.query, q: v.trim() || undefined, page: undefined } });
        load();
    }, 220);
});

const counts = computed(() => meta.value?.status_counts ?? {});
const tabs = computed(() => [
    { value: '', label: 'Mind', count: counts.value.all },
    { value: 'pending', label: 'Függőben', count: counts.value.pending },
    { value: 'paid', label: 'Fizetve', count: counts.value.paid },
    { value: 'shipped', label: 'Kiszállítva', count: counts.value.shipped },
    { value: 'cancelled', label: 'Lemondva', count: counts.value.cancelled },
]);

const drawer = computed(() => route.params.id);
const close = () => router.push({ name: 'orders', query: route.query });
const goPage = (p) => router.replace({ query: { ...route.query, page: p > 1 ? p : undefined } });
function onCreated(o) {
    load();
    router.replace({ name: 'orders', params: { id: o.id }, query: route.query });
}
</script>

<template>
    <PageHeader title="Rendelések" description="Állapotgép: függőben → fizetve → kiszállítva. Fizetéskor automatikus NAV-számla, lemondáskor a készlet visszakerül.">
        <Button variant="primary" to="/orders/new"><Plus />Új rendelés <Kbd class="ml-1 border-white/25 bg-white/15 text-accent-fg/80 max-sm:hidden">C</Kbd></Button>
    </PageHeader>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Segmented v-model="status" :options="tabs" label="Állapot szűrő" />
        <label class="relative sm:w-72">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
            <input v-model="search" type="search" class="field pl-9" placeholder="Sorszám vagy ügyfél…" aria-label="Rendelés keresése" />
        </label>
    </div>

    <div class="card overflow-hidden">
        <SkeletonRows v-if="!orders" :rows="8" />
        <template v-else-if="orders.length">
            <!-- Asztali táblázat -->
            <table class="hidden w-full md:table">
                <thead>
                    <tr class="border-b border-line text-left text-xs font-medium text-subtle">
                        <th class="py-3 pr-3 pl-5 font-medium">Ügyfél</th>
                        <th class="px-3 py-3 font-medium">Sorszám</th>
                        <th class="px-3 py-3 font-medium">Leadva</th>
                        <th class="px-3 py-3 text-right font-medium">Tétel</th>
                        <th class="px-3 py-3 font-medium">Állapot</th>
                        <th class="py-3 pr-5 pl-3 text-right font-medium">Nettó</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="o in orders" :key="o.id" class="group cursor-pointer transition hover:bg-surface-2/60" :class="String(o.id) === drawer ? 'bg-surface-2/80' : ''" @click="router.push({ name: 'orders', params: { id: o.id }, query: route.query })">
                        <td class="py-3 pr-3 pl-5">
                            <div class="flex items-center gap-3">
                                <Avatar :name="o.customer.company ?? o.customer.name" size="sm" />
                                <div class="min-w-0">
                                    <RouterLink :to="{ name: 'orders', params: { id: o.id }, query: route.query }" class="block truncate text-sm font-medium outline-none" @click.stop>{{ o.customer.company ?? o.customer.name }}</RouterLink>
                                    <div class="truncate text-xs text-subtle">{{ o.customer.name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 font-mono text-[13px] text-muted">{{ o.number }}</td>
                        <td class="px-3 py-3 text-sm whitespace-nowrap text-muted">{{ relative(o.placed_at) }}</td>
                        <td class="num px-3 py-3 text-right text-sm text-muted">{{ o.items_count }}</td>
                        <td class="px-3 py-3"><StatusBadge :status="o.status" :label="o.status_label" /></td>
                        <td class="num py-3 pr-5 pl-3 text-right text-sm font-semibold whitespace-nowrap">{{ money(o.total) }}</td>
                    </tr>
                </tbody>
            </table>
            <!-- Mobil kártyák -->
            <ul class="divide-y divide-line md:hidden">
                <li v-for="o in orders" :key="o.id">
                    <RouterLink :to="{ name: 'orders', params: { id: o.id }, query: route.query }" class="flex items-center gap-3 px-4 py-3.5">
                        <Avatar :name="o.customer.company ?? o.customer.name" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ o.customer.company ?? o.customer.name }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-subtle"><span class="font-mono">{{ o.number.slice(-5) }}</span>· {{ relative(o.placed_at) }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="num text-sm font-semibold">{{ money(o.total) }}</span>
                            <StatusBadge :status="o.status" :label="o.status_label" />
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </template>
        <Empty v-else :icon="Inbox" title="Nincs ilyen rendelés" :text="search ? `Nincs találat erre: „${search}”.` : 'Ebben az állapotban most nincs rendelés.'">
            <Button v-if="search || status" size="sm" @click="(search = ''), (status = '')">Szűrők törlése</Button>
        </Empty>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
        <span class="text-subtle">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</span>
        <div class="flex items-center gap-1.5">
            <Button size="sm" :disabled="meta.current_page === 1" aria-label="Előző oldal" @click="goPage(meta.current_page - 1)"><ChevronLeft /></Button>
            <span class="num px-2 text-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <Button size="sm" :disabled="meta.current_page === meta.last_page" aria-label="Következő oldal" @click="goPage(meta.current_page + 1)"><ChevronRight /></Button>
        </div>
    </div>

    <NewOrderDrawer v-if="drawer === 'new'" :customer-id="route.query.customer" @close="close" @created="onCreated" />
    <OrderDrawer v-else-if="drawer" :id="drawer" @close="close" @changed="load" />
</template>
