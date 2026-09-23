<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronRight, PackageSearch, Plus, Search } from 'lucide-vue-next';
import NewProductDrawer from '../components/inventory/NewProductDrawer.vue';
import ProductDrawer from '../components/inventory/ProductDrawer.vue';
import PageHeader from '../components/PageHeader.vue';
import Button from '../components/ui/Button.vue';
import Empty from '../components/ui/Empty.vue';
import Segmented from '../components/ui/Segmented.vue';
import SkeletonRows from '../components/ui/SkeletonRows.vue';
import { api } from '../lib/api.js';
import { compactMoney, money, number } from '../lib/format.js';
import { toastError } from '../lib/toast.js';

const route = useRoute();
const router = useRouter();
const products = ref(null);
const forecast = ref({});
const fmeta = ref(null);
const search = ref('');

async function load() {
    try {
        const [p, f] = await Promise.all([api('/inventory/products?per_page=100'), api('/inventory/forecast')]);
        products.value = p.data;
        forecast.value = Object.fromEntries(f.data.map((x) => [x.product_id, x]));
        fmeta.value = f.meta;
    } catch (e) {
        toastError(e);
    }
}
load();

const filter = computed({
    get: () => String(route.query.filter ?? ''),
    set: (v) => router.replace({ query: { ...route.query, filter: v || undefined } }),
});

const norm = (s) => s.toLocaleLowerCase('hu').normalize('NFD').replace(/\p{Diacritic}/gu, '');
const rows = computed(() => {
    const q = norm(search.value.trim());
    return (products.value ?? [])
        .filter((p) => (filter.value === 'low' ? p.is_low_stock : filter.value === 'out' ? p.stock === 0 : filter.value === 'reorder' ? forecast.value[p.id]?.suggested_reorder > 0 : true))
        .filter((p) => !q || norm(`${p.name} ${p.sku}`).includes(q));
});

const stats = computed(() => {
    const ps = products.value ?? [];
    return {
        count: ps.length,
        value: ps.reduce((s, p) => s + p.stock * p.unit_price, 0),
        low: ps.filter((p) => p.is_low_stock).length,
        out: ps.filter((p) => p.stock === 0).length,
        reorder: Object.values(forecast.value).filter((f) => f.suggested_reorder > 0).length,
    };
});

const tabs = computed(() => [
    { value: '', label: 'Mind', count: stats.value.count },
    { value: 'reorder', label: 'Rendelni kell', count: stats.value.reorder },
    { value: 'low', label: 'Alacsony', count: stats.value.low },
    { value: 'out', label: 'Elfogyott', count: stats.value.out },
]);

// A sáv a készletet az újrarendelési szint négyszereséhez méri: így az alacsony szint mindig látható.
const fill = (p) => Math.min(100, (p.stock / Math.max(p.reorder_level * 4, 1)) * 100);
const tone = (p) => (p.stock === 0 ? 'danger' : p.is_low_stock ? 'warning' : 'success');
const toneBg = { danger: 'bg-danger', warning: 'bg-warning', success: 'bg-success' };
const toneText = { danger: 'text-danger', warning: 'text-warning', success: '' };
const coverClass = (f) => (f?.days_of_cover === 0 ? 'text-danger font-semibold' : f?.days_of_cover <= (fmeta.value?.lead_time_days ?? 7) ? 'text-warning font-semibold' : 'text-muted');

const drawer = computed(() => route.params.id);
const close = () => router.push({ name: 'inventory', query: route.query });
const open = (p) => router.push({ name: 'inventory', params: { id: p.id }, query: route.query });
function onCreated(p) {
    load();
    router.replace({ name: 'inventory', params: { id: p.id }, query: route.query });
}
</script>

<template>
    <PageHeader title="Készlet" description="Minden változás változtathatatlan naplóból számolódik. A „Kitart” és a rendelési javaslat az elmúlt 30 nap eladási ütemén alapul.">
        <Button variant="primary" to="/inventory/new"><Plus />Új termék</Button>
    </PageHeader>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><div class="text-[13px] text-muted">Termékek</div><div class="num mt-1.5 text-xl font-semibold">{{ products ? stats.count : '–' }}</div></div>
        <div class="card p-4"><div class="text-[13px] text-muted">Készletérték (nettó)</div><div class="num mt-1.5 text-xl font-semibold">{{ products ? compactMoney(stats.value) : '–' }}</div></div>
        <button class="card p-4 text-left transition hover:border-line-strong" @click="filter = 'reorder'"><div class="text-[13px] text-muted">Rendelési javaslat</div><div class="num mt-1.5 text-xl font-semibold text-accent">{{ products ? `${stats.reorder} termék` : '–' }}</div></button>
        <button class="card p-4 text-left transition hover:border-line-strong" @click="filter = 'out'"><div class="text-[13px] text-muted">Elfogyott</div><div class="num mt-1.5 text-xl font-semibold" :class="stats.out ? 'text-danger' : ''">{{ products ? stats.out : '–' }}</div></button>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Segmented v-model="filter" :options="tabs" label="Készletszűrő" />
        <label class="relative sm:w-72">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
            <input v-model="search" type="search" class="field pl-9" placeholder="Név vagy cikkszám…" aria-label="Termék keresése" />
        </label>
    </div>

    <div class="card overflow-hidden">
        <SkeletonRows v-if="!products" :rows="8" />
        <template v-else-if="rows.length">
            <table class="hidden w-full md:table">
                <thead>
                    <tr class="border-b border-line text-left text-xs text-subtle">
                        <th class="py-3 pr-3 pl-5 font-medium">Termék</th>
                        <th class="px-3 py-3 text-right font-medium">Nettó ár</th>
                        <th class="w-[26%] px-3 py-3 font-medium">Készlet</th>
                        <th class="px-3 py-3 text-right font-medium">Kitart</th>
                        <th class="px-3 py-3 text-right font-medium">Javasolt rendelés</th>
                        <th class="w-10 py-3 pr-4" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="p in rows" :key="p.id" class="group cursor-pointer transition hover:bg-surface-2/60" :class="String(p.id) === drawer ? 'bg-surface-2/80' : ''" @click="open(p)">
                        <td class="py-3 pr-3 pl-5">
                            <RouterLink :to="{ name: 'inventory', params: { id: p.id }, query: route.query }" class="block text-sm font-medium" @click.stop>{{ p.name }}</RouterLink>
                            <div class="font-mono text-xs text-subtle">{{ p.sku }}<span v-if="!p.is_active" class="ml-2 font-sans">· inaktív</span></div>
                        </td>
                        <td class="num px-3 py-3 text-right text-sm text-muted">{{ money(p.unit_price) }}</td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" :class="toneBg[tone(p)]" :style="{ width: `${Math.max(fill(p), p.stock ? 3 : 0)}%` }" /></div>
                                <span class="num w-14 text-right text-sm" :class="[toneText[tone(p)], p.is_low_stock ? 'font-semibold' : '']">{{ number(p.stock) }} db</span>
                            </div>
                        </td>
                        <td class="num px-3 py-3 text-right text-sm whitespace-nowrap" :class="coverClass(forecast[p.id])" :title="forecast[p.id]?.stockout_on ? `Várható kifogyás: ${forecast[p.id].stockout_on}` : 'Nincs eladás az időszakban'">
                            {{ forecast[p.id]?.days_of_cover === null || !forecast[p.id] ? '–' : forecast[p.id].days_of_cover === 0 ? 'elfogyott' : `${forecast[p.id].days_of_cover} nap` }}
                        </td>
                        <td class="num px-3 py-3 text-right text-sm">
                            <span v-if="forecast[p.id]?.suggested_reorder" class="rounded-md px-2 py-0.5 font-semibold soft-accent">{{ forecast[p.id].suggested_reorder }} db</span>
                            <span v-else class="text-subtle">–</span>
                        </td>
                        <td class="py-3 pr-4 text-subtle"><ChevronRight class="size-4 opacity-0 transition group-hover:opacity-100" /></td>
                    </tr>
                </tbody>
            </table>
            <ul class="divide-y divide-line md:hidden">
                <li v-for="p in rows" :key="p.id">
                    <RouterLink :to="{ name: 'inventory', params: { id: p.id }, query: route.query }" class="block px-4 py-3.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="truncate text-sm font-medium">{{ p.name }}</span>
                            <span class="num shrink-0 text-sm" :class="[toneText[tone(p)], p.is_low_stock ? 'font-semibold' : '']">{{ p.stock }} db</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" :class="toneBg[tone(p)]" :style="{ width: `${Math.max(fill(p), p.stock ? 3 : 0)}%` }" /></div>
                        <div class="mt-1.5 flex justify-between text-xs text-subtle">
                            <span class="font-mono">{{ p.sku }}</span>
                            <span v-if="forecast[p.id]?.suggested_reorder" class="font-medium text-accent">rendelj {{ forecast[p.id].suggested_reorder }} db-ot</span>
                            <span v-else>{{ money(p.unit_price) }}</span>
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </template>
        <Empty v-else :icon="PackageSearch" title="Nincs ilyen termék" :text="search ? `Nincs találat erre: „${search}”.` : 'Ebben a szűrőben most nincs termék – ez jó hír.'">
            <Button size="sm" @click="(search = ''), (filter = '')">Szűrők törlése</Button>
        </Empty>
    </div>

    <NewProductDrawer v-if="drawer === 'new'" @close="close" @created="onCreated" />
    <ProductDrawer v-else-if="drawer" :id="drawer" :forecast="forecast[drawer]" :meta="fmeta" @close="close" @changed="load" />
</template>
