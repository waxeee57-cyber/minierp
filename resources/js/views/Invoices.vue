<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, FileText, Search, ShieldCheck } from 'lucide-vue-next';
import InvoiceDrawer from '../components/invoices/InvoiceDrawer.vue';
import PageHeader from '../components/PageHeader.vue';
import Avatar from '../components/ui/Avatar.vue';
import Button from '../components/ui/Button.vue';
import Empty from '../components/ui/Empty.vue';
import SkeletonRows from '../components/ui/SkeletonRows.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { api } from '../lib/api.js';
import { compactMoney, isoDate, money, number } from '../lib/format.js';
import { toastError } from '../lib/toast.js';

const route = useRoute();
const router = useRouter();
const invoices = ref(null);
const meta = ref(null);
const search = ref('');
const page = computed(() => Number(route.query.page ?? 1));

async function load() {
    const q = new URLSearchParams({ page: page.value, per_page: 15, ...(search.value.trim() && { search: search.value.trim() }) });
    try {
        const res = await api(`/invoicing/invoices?${q}`);
        invoices.value = res.data;
        meta.value = res.meta;
    } catch (e) {
        toastError(e);
    }
}
watch(page, load, { immediate: true });
let t;
watch(search, () => {
    clearTimeout(t);
    t = setTimeout(() => (page.value === 1 ? load() : router.replace({ query: { ...route.query, page: undefined } })), 220);
});

const drawer = computed(() => route.params.id);
const goPage = (p) => router.replace({ query: { ...route.query, page: p > 1 ? p : undefined } });
</script>

<template>
    <PageHeader title="Számlák" description="Fizetéskor automatikusan kiállítva. Minden számla NAV Online Számla 3.0 InvoiceData XML-ként is elkészül, a hivatalos XSD-séma ellen validálva." />

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><div class="text-[13px] text-muted">Kiállított számla</div><div class="num mt-1.5 text-xl font-semibold">{{ meta ? number(meta.totals.count) : '–' }}</div></div>
        <div class="card p-4"><div class="text-[13px] text-muted">Bruttó összesen</div><div class="num mt-1.5 text-xl font-semibold">{{ meta ? compactMoney(meta.totals.gross) : '–' }}</div></div>
        <div class="card p-4"><div class="text-[13px] text-muted">Ebből ÁFA</div><div class="num mt-1.5 text-xl font-semibold">{{ meta ? compactMoney(meta.totals.vat) : '–' }}</div></div>
        <div class="card p-4">
            <div class="text-[13px] text-muted">NAV XSD-validáció</div>
            <div class="mt-1.5 flex items-center gap-2 text-xl font-semibold" :class="meta?.totals.invalid ? 'text-danger' : 'text-success'">
                <ShieldCheck class="size-5" />{{ meta ? (meta.totals.invalid ? `${meta.totals.invalid} hibás` : '100% megfelel') : '–' }}
            </div>
        </div>
    </div>

    <div class="mb-4 flex justify-end">
        <label class="relative w-full sm:w-72">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
            <input v-model="search" type="search" class="field pl-9" placeholder="Számlaszám…" aria-label="Számla keresése" />
        </label>
    </div>

    <div class="card overflow-hidden">
        <SkeletonRows v-if="!invoices" :rows="8" />
        <template v-else-if="invoices.length">
            <table class="hidden w-full md:table">
                <thead>
                    <tr class="border-b border-line text-left text-xs text-subtle">
                        <th class="py-3 pr-3 pl-5 font-medium">Számlaszám</th>
                        <th class="px-3 py-3 font-medium">Vevő</th>
                        <th class="px-3 py-3 font-medium">Kiállítva</th>
                        <th class="px-3 py-3 font-medium">NAV</th>
                        <th class="px-3 py-3 text-right font-medium">Nettó</th>
                        <th class="py-3 pr-5 pl-3 text-right font-medium">Bruttó</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="i in invoices" :key="i.id" class="cursor-pointer transition hover:bg-surface-2/60" :class="String(i.id) === drawer ? 'bg-surface-2/80' : ''" @click="router.push({ name: 'invoices', params: { id: i.id }, query: route.query })">
                        <td class="py-3 pr-3 pl-5">
                            <RouterLink :to="{ name: 'invoices', params: { id: i.id }, query: route.query }" class="font-mono text-[13px] font-medium" @click.stop>{{ i.number }}</RouterLink>
                            <div class="font-mono text-xs text-subtle">{{ i.order_number }}</div>
                        </td>
                        <td class="px-3 py-3"><div class="flex items-center gap-2.5"><Avatar :name="i.buyer?.name" size="sm" /><span class="truncate text-sm">{{ i.buyer?.name }}</span></div></td>
                        <td class="num px-3 py-3 text-sm text-muted">{{ isoDate(i.issue_date) }}</td>
                        <td class="px-3 py-3"><StatusBadge :status="i.nav_status" :label="i.nav_status_label" /></td>
                        <td class="num px-3 py-3 text-right text-sm text-muted">{{ money(i.net_total) }}</td>
                        <td class="num py-3 pr-5 pl-3 text-right text-sm font-semibold">{{ money(i.gross_total) }}</td>
                    </tr>
                </tbody>
            </table>
            <ul class="divide-y divide-line md:hidden">
                <li v-for="i in invoices" :key="i.id">
                    <RouterLink :to="{ name: 'invoices', params: { id: i.id } }" class="flex items-center gap-3 px-4 py-3.5">
                        <div class="grid size-9 shrink-0 place-items-center rounded-lg soft-accent"><FileText class="size-4" /></div>
                        <div class="min-w-0 flex-1">
                            <div class="font-mono text-[13px] font-medium">{{ i.number }}</div>
                            <div class="truncate text-xs text-subtle">{{ i.buyer?.name }} · {{ isoDate(i.issue_date) }}</div>
                        </div>
                        <span class="num text-sm font-semibold">{{ money(i.gross_total) }}</span>
                    </RouterLink>
                </li>
            </ul>
        </template>
        <Empty v-else :icon="FileText" title="Nincs számla" :text="search ? `Nincs találat erre: „${search}”.` : 'Az első fizetett rendelésnél automatikusan elkészül.'" />
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
        <span class="text-subtle">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</span>
        <div class="flex items-center gap-1.5">
            <Button size="sm" :disabled="meta.current_page === 1" aria-label="Előző oldal" @click="goPage(meta.current_page - 1)"><ChevronLeft /></Button>
            <span class="num px-2 text-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <Button size="sm" :disabled="meta.current_page === meta.last_page" aria-label="Következő oldal" @click="goPage(meta.current_page + 1)"><ChevronRight /></Button>
        </div>
    </div>

    <InvoiceDrawer v-if="drawer" :id="drawer" @close="router.push({ name: 'invoices', query: route.query })" />
</template>
