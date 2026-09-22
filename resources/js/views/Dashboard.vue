<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, date, money } from '../api.js';
import StatusBadge from '../components/StatusBadge.vue';
import AskErp from '../components/AskErp.vue';

const emit = defineEmits(['navigate']);
const stats = ref(null);
const orders = ref([]);
const forecast = ref([]);
const forecastMeta = ref(null);

onMounted(async () => {
    const [s, o, f] = await Promise.all([api('/dashboard'), api('/orders?per_page=6'), api('/inventory/forecast')]);
    stats.value = s.data;
    orders.value = o.data;
    forecast.value = f.data.filter((x) => x.days_of_cover !== null).slice(0, 6);
    forecastMeta.value = f.meta;
});

const maxDay = computed(() => Math.max(1, ...(stats.value?.revenue_by_day ?? []).map((d) => d.total)));
</script>

<template>
    <header class="mb-6">
        <h1 class="text-xl font-semibold">Vezérlőpult</h1>
        <p class="text-sm text-slate-500">Rendelések, készlet és ügyfélteendők egy helyen.</p>
    </header>

    <div v-if="stats" class="space-y-6">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card p-4">
                <div class="text-xs text-slate-500">Bevétel ebben a hónapban</div>
                <div class="num mt-1 text-xl font-semibold">{{ money(stats.revenue_this_month) }}</div>
                <div class="text-xs text-slate-400">fizetett + kiszállított</div>
            </div>
            <button class="card p-4 text-left hover:border-brand-500" @click="emit('navigate', 'orders', { status: 'pending' })">
                <div class="text-xs text-slate-500">Függő rendelés</div>
                <div class="num mt-1 text-xl font-semibold">{{ stats.orders_by_status.pending }}</div>
                <div class="text-xs text-slate-400">fizetésre vár</div>
            </button>
            <button class="card p-4 text-left hover:border-brand-500" @click="emit('navigate', 'inventory', { lowStock: true })">
                <div class="text-xs text-slate-500">Alacsony készlet</div>
                <div class="num mt-1 text-xl font-semibold" :class="stats.low_stock_count ? 'text-amber-600' : ''">{{ stats.low_stock_count }}</div>
                <div class="text-xs text-slate-400">termék újrarendelési szinten</div>
            </button>
            <button class="card p-4 text-left hover:border-brand-500" @click="emit('navigate', 'customers')">
                <div class="text-xs text-slate-500">Nyitott teendő</div>
                <div class="num mt-1 text-xl font-semibold">{{ stats.open_tasks }}</div>
                <div class="text-xs" :class="stats.overdue_tasks ? 'text-red-600' : 'text-slate-400'">{{ stats.overdue_tasks }} lejárt</div>
            </button>
        </div>

        <div class="card p-5">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-sm font-semibold">Bevétel, elmúlt 14 nap</h2>
                <span class="text-xs text-slate-400">napi összeg</span>
            </div>
            <div class="flex h-40 items-end gap-1.5">
                <div v-for="d in stats.revenue_by_day" :key="d.date" class="group relative flex h-full flex-1 flex-col justify-end">
                    <div class="rounded-t bg-brand-500/80 transition group-hover:bg-brand-600" :style="{ height: `${Math.max(2, (d.total / maxDay) * 100)}%` }" />
                    <div class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded bg-slate-900 px-1.5 py-0.5 text-[11px] whitespace-nowrap text-white group-hover:block">{{ money(d.total) }}</div>
                </div>
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-slate-400">
                <span>{{ date(stats.revenue_by_day[0].date) }}</span><span>ma</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="card overflow-hidden lg:col-span-3">
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="text-sm font-semibold">Legutóbbi rendelések</h2>
                    <button class="text-xs font-medium text-brand-600" @click="emit('navigate', 'orders')">Összes →</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px]">
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="o in orders" :key="o.id">
                                <td class="td font-mono text-xs whitespace-nowrap">{{ o.number }}</td>
                                <td class="td">{{ o.customer.company ?? o.customer.name }}</td>
                                <td class="td"><StatusBadge :status="o.status" :label="o.status_label" /></td>
                                <td class="td num text-right font-medium whitespace-nowrap">{{ money(o.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card lg:col-span-2">
                <div class="flex items-baseline justify-between px-4 py-3">
                    <h2 class="text-sm font-semibold">Kifogyási előrejelzés</h2>
                    <span v-if="forecastMeta" class="text-[11px] text-slate-400">{{ forecastMeta.window_days }} napos eladásból · átfutás {{ forecastMeta.lead_time_days }} nap</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="p in forecast" :key="p.product_id" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <div class="min-w-0">
                            <div class="truncate">{{ p.name }}</div>
                            <div class="text-xs text-slate-500">{{ p.stock }} db · {{ p.daily_demand.toLocaleString('hu-HU') }} db/nap</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="num font-medium" :class="p.days_of_cover === 0 ? 'text-red-600' : p.days_of_cover <= forecastMeta.lead_time_days ? 'text-amber-600' : 'text-slate-700'">
                                {{ p.days_of_cover === 0 ? 'elfogyott' : `${p.days_of_cover} nap` }}
                            </div>
                            <div v-if="p.suggested_reorder" class="text-xs text-slate-500">rendelj {{ p.suggested_reorder }} db-ot</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <AskErp />
    </div>
    <div v-else class="text-sm text-slate-400">Betöltés…</div>
</template>
