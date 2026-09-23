<script setup>
import { computed, ref, watch } from 'vue';
import { AlarmClock, ArrowRight, CalendarClock, Coins, Package, Plus, ShoppingCart, TriangleAlert, Wallet } from 'lucide-vue-next';
import AskErp from '../components/AskErp.vue';
import PageHeader from '../components/PageHeader.vue';
import AreaChart from '../components/ui/AreaChart.vue';
import Avatar from '../components/ui/Avatar.vue';
import Button from '../components/ui/Button.vue';
import Delta from '../components/ui/Delta.vue';
import Empty from '../components/ui/Empty.vue';
import Segmented from '../components/ui/Segmented.vue';
import Stat from '../components/ui/Stat.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { api } from '../lib/api.js';
import { compactMoney, decimal, delta, money, number, relative, today } from '../lib/format.js';
import { toastError } from '../lib/toast.js';

const days = ref(30);
const d = ref(null);
const orders = ref(null);
const forecast = ref(null);

async function load() {
    try {
        const [s, o, f] = await Promise.all([api(`/dashboard?days=${days.value}`), api('/orders?per_page=7'), api('/inventory/forecast')]);
        d.value = s.data;
        orders.value = o.data;
        forecast.value = { meta: f.meta, items: f.data.filter((x) => x.days_of_cover !== null).slice(0, 6) };
    } catch (e) {
        toastError(e);
    }
}
watch(days, load, { immediate: true });

const hour = new Date().getHours();
const greeting = hour < 10 ? 'Jó reggelt!' : hour < 18 ? 'Szép napot!' : 'Jó estét!';

const summary = computed(() => {
    if (!d.value) return '\u00a0'; // helyfoglaló: a fejléc magassága betöltéskor sem ugrik
    const parts = [];
    if (d.value.orders_by_status.pending) parts.push(`${d.value.orders_by_status.pending} rendelés vár fizetésre`);
    if (d.value.out_of_stock_count) parts.push(`${d.value.out_of_stock_count} termék elfogyott`);
    else if (d.value.low_stock_count) parts.push(`${d.value.low_stock_count} termék alacsony készleten`);
    if (d.value.overdue_tasks) parts.push(`${d.value.overdue_tasks} lejárt teendő`);
    return parts.length ? `${parts.join(', ')}.` : 'Minden rendben: nincs függő teendő.';
});

const status = computed(() => {
    if (!d.value) return [];
    const s = d.value.orders_by_status;
    const total = Object.values(s).reduce((a, b) => a + b, 0) || 1;
    return [
        { key: 'pending', label: 'Függőben', tone: 'warning', n: s.pending },
        { key: 'paid', label: 'Fizetve', tone: 'info', n: s.paid },
        { key: 'shipped', label: 'Kiszállítva', tone: 'success', n: s.shipped },
        { key: 'cancelled', label: 'Lemondva', tone: 'line-strong', n: s.cancelled },
    ].map((x) => ({ ...x, pct: (x.n / total) * 100 }));
});

const topMax = computed(() => Math.max(1, ...(d.value?.top_customers ?? []).map((c) => c.revenue)));
const toneText = { danger: 'text-danger', warning: 'text-warning', success: 'text-success' };
const toneBg = { danger: 'bg-danger', warning: 'bg-warning', success: 'bg-success' };
const coverTone = (p) => (p.days_of_cover === 0 ? 'danger' : p.days_of_cover <= forecast.value.meta.lead_time_days ? 'warning' : 'success');
</script>

<template>
    <PageHeader :eyebrow="today()" :title="greeting" :description="summary" reserve>
        <Segmented v-model="days" label="Időszak" :options="[{ value: 7, label: '7 nap' }, { value: 30, label: '30 nap' }, { value: 90, label: '90 nap' }]" />
        <Button variant="primary" to="/orders/new"><Plus />Új rendelés</Button>
    </PageHeader>

    <div class="space-y-5">
        <AskErp />

        <!-- KPI-k -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Stat label="Realizált bevétel" :icon="Wallet" :loading="!d" with-spark :value="d && money(d.revenue_this_month)" hint="az előző hónap azonos időszakához képest" :delta="d ? delta(d.revenue_this_month, d.revenue_prev_month) : undefined" :spark="d?.series.map((x) => x.realized)" />
            <Stat label="Rendelések" :icon="ShoppingCart" :loading="!d" with-spark :value="d && number(d.orders_this_month)" :hint="d && `e hónapban · átlagos kosár ${compactMoney(d.average_order)}`" :delta="d ? delta(d.orders_this_month, d.orders_prev_month) : undefined" :spark="d?.series.map((x) => x.booked)" tone="info" />
            <Stat label="Fizetésre vár" :icon="Coins" :loading="!d" with-spark :value="d && money(d.pending_value)" :hint="d && `${d.orders_by_status.pending} függő rendelés · kattints a listáért`" to="/orders?status=pending" />
            <Stat label="Készletriasztás" :icon="TriangleAlert" :loading="!d" with-spark :value="d && `${d.low_stock_count} termék`" :hint="d && (d.out_of_stock_count ? `${d.out_of_stock_count} már elfogyott · rendelési javaslat kész` : 'újrarendelési szinten')" to="/inventory?filter=low" :alert="d?.out_of_stock_count > 0" />
        </div>

        <!-- Bevétel grafikon -->
        <section class="card p-4 sm:p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[15px] font-semibold">Bevétel</h2>
                    <p class="text-[13px] text-subtle">Leadott rendelések értéke és a ténylegesen realizált (fizetett + kiszállított) bevétel naponta.</p>
                </div>
                <div v-if="d" class="flex gap-6">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs text-subtle"><span class="h-0.5 w-3 border-t-2 border-dashed border-subtle" />Leadott</div>
                        <div class="num mt-0.5 flex items-center gap-2 text-lg font-semibold">{{ compactMoney(d.totals.booked) }}<Delta :value="delta(d.totals.booked, d.totals.booked_prev)" /></div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 text-xs text-subtle"><span class="h-0.5 w-3 rounded bg-accent" />Realizált</div>
                        <div class="num mt-0.5 flex items-center gap-2 text-lg font-semibold">{{ compactMoney(d.totals.realized) }}<Delta :value="delta(d.totals.realized, d.totals.realized_prev)" /></div>
                    </div>
                </div>
            </div>
            <AreaChart
                v-if="d"
                :points="d.series"
                :series="[
                    { key: 'booked', label: 'Leadott', tone: 'fg-subtle', dashed: true, fill: false },
                    { key: 'realized', label: 'Realizált', tone: 'accent' },
                ]"
                :height="260"
            />
            <div v-else class="skeleton h-[260px]" />
            <p v-if="d" class="mt-2 text-right text-xs text-subtle">Változás az előző {{ d.days }} naphoz képest</p>
        </section>

        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Legutóbbi rendelések -->
            <section class="card overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between px-4 pt-4 pb-2 sm:px-5">
                    <h2 class="text-[15px] font-semibold">Legutóbbi rendelések</h2>
                    <RouterLink to="/orders" class="flex items-center gap-1 text-[13px] font-medium text-accent hover:underline">Összes <ArrowRight class="size-3.5" /></RouterLink>
                </div>
                <ul v-if="orders" class="divide-y divide-line">
                    <li v-for="o in orders" :key="o.id">
                        <RouterLink :to="`/orders/${o.id}`" class="flex items-center gap-3 px-4 py-3 transition hover:bg-surface-2/60 sm:px-5">
                            <Avatar :name="o.customer.company ?? o.customer.name" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ o.customer.company ?? o.customer.name }}</div>
                                <div class="truncate text-xs text-subtle"><span class="font-mono">{{ o.number }}</span> · {{ relative(o.placed_at) }}</div>
                            </div>
                            <StatusBadge class="max-sm:hidden" :status="o.status" :label="o.status_label" />
                            <div class="num w-24 text-right text-sm font-semibold sm:w-28">{{ money(o.total) }}</div>
                        </RouterLink>
                    </li>
                </ul>
                <div v-else class="space-y-3 p-5"><div v-for="i in 5" :key="i" class="skeleton h-9" /></div>
            </section>

            <!-- Kifogyási előrejelzés -->
            <section class="card overflow-hidden">
                <div class="px-4 pt-4 pb-2 sm:px-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-[15px] font-semibold">Kifogyási előrejelzés</h2>
                        <Package class="size-4 text-subtle" />
                    </div>
                    <p v-if="forecast" class="text-xs text-subtle">{{ forecast.meta.window_days }} napos eladási ütemből · átfutás {{ forecast.meta.lead_time_days }} nap</p>
                </div>
                <ul v-if="forecast" class="divide-y divide-line">
                    <li v-for="p in forecast.items" :key="p.product_id">
                        <RouterLink :to="`/inventory/${p.product_id}`" class="block px-4 py-2.5 transition hover:bg-surface-2/60 sm:px-5">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="truncate text-sm font-medium">{{ p.name }}</span>
                                <span class="num shrink-0 text-sm font-semibold" :class="toneText[coverTone(p)]">{{ p.days_of_cover === 0 ? 'elfogyott' : `${p.days_of_cover} nap` }}</span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2.5">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                                    <div class="h-full rounded-full" :class="toneBg[coverTone(p)]" :style="{ width: `${Math.max(3, Math.min(100, (p.days_of_cover / 30) * 100))}%` }" />
                                </div>
                                <span class="shrink-0 text-xs text-subtle">{{ p.suggested_reorder ? `rendelj ${p.suggested_reorder} db-ot` : `${p.stock} db` }}</span>
                            </div>
                        </RouterLink>
                    </li>
                </ul>
                <div v-else class="space-y-3 p-5"><div v-for="i in 5" :key="i" class="skeleton h-8" /></div>
            </section>
        </div>

        <div v-if="d" class="grid gap-5 lg:grid-cols-3">
            <!-- Top ügyfelek -->
            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-[15px] font-semibold">Top ügyfelek <span class="font-normal text-subtle">· {{ d.days }} nap</span></h2>
                <ul class="space-y-3">
                    <li v-for="c in d.top_customers" :key="c.id">
                        <RouterLink :to="`/customers/${c.id}`" class="group block">
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="truncate font-medium group-hover:text-accent">{{ c.name }}</span>
                                <span class="num shrink-0 font-semibold">{{ compactMoney(c.revenue) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-accent/80" :style="{ width: `${(c.revenue / topMax) * 100}%` }" /></div>
                        </RouterLink>
                    </li>
                </ul>
                <Empty v-if="!d.top_customers.length" title="Még nincs realizált bevétel." />
            </section>

            <!-- Teendők -->
            <section class="card p-4 sm:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-[15px] font-semibold">Teendők</h2>
                    <span class="text-xs text-subtle">{{ d.open_tasks }} nyitott</span>
                </div>
                <ul v-if="d.tasks.length" class="space-y-1">
                    <li v-for="t in d.tasks" :key="t.id">
                        <RouterLink :to="`/customers/${t.customer_id}`" class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2 transition hover:bg-surface-2/60">
                            <component :is="t.overdue ? AlarmClock : CalendarClock" class="mt-0.5 size-4 shrink-0" :class="t.overdue ? 'text-danger' : 'text-subtle'" />
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ t.subject }}</div>
                                <div class="text-xs" :class="t.overdue ? 'text-danger' : 'text-subtle'">{{ t.customer }} · {{ t.overdue ? 'lejárt, ' : '' }}{{ relative(t.due_at) }}</div>
                            </div>
                        </RouterLink>
                    </li>
                </ul>
                <Empty v-else title="Nincs nyitott teendő." text="Az utókövetés-ütemező automatikusan létrehozza őket." />
            </section>

            <!-- Állapot szerinti megoszlás -->
            <section class="card p-4 sm:p-5">
                <h2 class="mb-4 text-[15px] font-semibold">Rendelések állapot szerint</h2>
                <div class="flex h-2.5 overflow-hidden rounded-full bg-surface-2">
                    <div v-for="s in status" :key="s.key" class="h-full first:rounded-l-full last:rounded-r-full" :style="{ width: `${s.pct}%`, background: `var(--${s.tone})` }" />
                </div>
                <ul class="mt-4 space-y-2">
                    <li v-for="s in status" :key="s.key">
                        <RouterLink :to="{ path: '/orders', query: { status: s.key } }" class="flex items-center gap-2.5 text-sm hover:text-accent">
                            <span class="size-2 rounded-full" :style="{ background: `var(--${s.tone})` }" />
                            <span class="flex-1">{{ s.label }}</span>
                            <span class="num text-subtle">{{ decimal(s.pct) }}%</span>
                            <span class="num w-8 text-right font-semibold">{{ s.n }}</span>
                        </RouterLink>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
