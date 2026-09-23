<script setup>
import { computed, ref, watch } from 'vue';
import { Check, CircleX, Copy, ExternalLink, KeyRound, Megaphone, Rss, ShoppingBag, Store, Webhook } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import Badge from '../components/ui/Badge.vue';
import Empty from '../components/ui/Empty.vue';
import Segmented from '../components/ui/Segmented.vue';
import SkeletonRows from '../components/ui/SkeletonRows.vue';
import Stat from '../components/ui/Stat.vue';
import StatusBadge from '../components/ui/StatusBadge.vue';
import { api } from '../lib/api.js';
import { compactMoney, money, number, relative } from '../lib/format.js';
import { toast, toastError } from '../lib/toast.js';

const days = ref(30);
const report = ref(null);
const orders = ref(null);
const filter = ref('');
const copied = ref(null);

async function loadReport() {
    try {
        report.value = (await api(`/channels/report?days=${days.value}`)).data;
    } catch (e) {
        toastError(e);
    }
}
async function loadOrders() {
    try {
        orders.value = (await api(`/channels/orders?per_page=25${filter.value ? `&status=${filter.value}` : ''}`)).data;
    } catch (e) {
        toastError(e);
    }
}
watch(days, loadReport, { immediate: true });
watch(filter, loadOrders, { immediate: true });

const sourceLabels = { google: 'Google Ads', facebook: 'Facebook / Instagram', arukereso: 'Árukereső', newsletter: 'Hírlevél', direct: 'Közvetlen / ismeretlen' };
const sourceMax = computed(() => Math.max(1, ...(report.value?.by_source ?? []).map((s) => s.revenue)));
const avg = computed(() => (report.value?.orders ? Math.round(report.value.revenue / report.value.orders) : 0));

async function copy(text, key) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 1500);
    } catch {
        toast('A vágólap nem elérhető ebben a böngészőben.', { tone: 'danger' });
    }
}
</script>

<template>
    <PageHeader title="Webshop" description="Shopify- és WooCommerce-rendelések aláírt webhookkal, ugyanazon az úton, mint a kézi rendelés: készletfoglalás, CRM-idővonal, fizetettnél automatikus NAV-számla. Termékfeedek Google Shoppingnak és Árukeresőnek.">
        <Segmented v-model="days" label="Időszak" :options="[{ value: 7, label: '7 nap' }, { value: 30, label: '30 nap' }, { value: 90, label: '90 nap' }]" />
    </PageHeader>

    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Stat label="Webshop-bevétel" :icon="ShoppingBag" :loading="!report" :value="report && money(report.revenue)" :hint="report && `utolsó ${report.days} nap · nettó`" />
            <Stat label="Importált rendelés" :icon="Store" :loading="!report" :value="report && number(report.orders)" :hint="report && `átlagos kosár ${compactMoney(avg)}`" />
            <Stat label="Kézi döntésre vár" :icon="CircleX" :loading="!report" :value="report && number(report.rejected)" :hint="'ismeretlen cikkszám vagy készlethiány'" :alert="report?.rejected > 0" />
            <Stat label="Feedben lévő termék" :icon="Rss" :loading="!report" :value="report && number(report.feed_products)" hint="aktív, márkával rendelkező termékek" />
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Marketing: forrás szerinti bevétel -->
            <section class="card p-4 sm:p-5 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-[15px] font-semibold"><Megaphone class="size-4 text-subtle" />Bevétel marketingforrás szerint</h2>
                    <span class="text-xs text-subtle max-sm:hidden">UTM-paraméterekből</span>
                </div>
                <ul v-if="report?.by_source.length" class="space-y-3.5">
                    <li v-for="s in report.by_source" :key="s.key">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium">{{ sourceLabels[s.key] ?? s.key }}</span>
                            <span class="num text-subtle">{{ s.orders }} rendelés · <span class="font-semibold text-fg">{{ money(s.revenue) }}</span></span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-accent/85" :style="{ width: `${(s.revenue / sourceMax) * 100}%` }" /></div>
                    </li>
                </ul>
                <div v-else-if="!report" class="space-y-3"><div v-for="i in 4" :key="i" class="skeleton h-8" /></div>
                <Empty v-else title="Ebben az időszakban nem jött webshop-rendelés." />
            </section>

            <!-- Csatornák -->
            <section class="card p-4 sm:p-5">
                <h2 class="mb-4 text-[15px] font-semibold">Csatornák</h2>
                <ul v-if="report" class="space-y-3">
                    <li v-for="c in report.by_channel" :key="c.key" class="flex items-center justify-between rounded-xl border border-line px-3.5 py-3">
                        <div>
                            <div class="text-sm font-medium">{{ c.label }}</div>
                            <div class="text-xs text-subtle">{{ c.orders }} rendelés</div>
                        </div>
                        <div class="num text-sm font-semibold">{{ compactMoney(c.revenue) }}</div>
                    </li>
                    <li v-if="!report.by_channel.length" class="text-sm text-subtle">Nincs adat.</li>
                </ul>
                <div v-else class="space-y-3"><div v-for="i in 2" :key="i" class="skeleton h-14" /></div>
            </section>
        </div>

        <!-- Beérkezett rendelések -->
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 pt-4 pb-3 sm:px-5">
                <h2 class="text-[15px] font-semibold">Beérkezett webshop-rendelések</h2>
                <Segmented v-model="filter" label="Állapot" :options="[{ value: '', label: 'Mind' }, { value: 'imported', label: 'Importálva' }, { value: 'rejected', label: 'Elutasítva' }]" />
            </div>
            <SkeletonRows v-if="!orders" :rows="6" />
            <ul v-else-if="orders.length" class="divide-y divide-line border-t border-line">
                <li v-for="o in orders" :key="o.id" class="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-3 sm:px-5">
                    <Badge :tone="o.channel === 'shopify' ? 'success' : 'info'">{{ o.channel_label }}</Badge>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium"><span class="font-mono text-[13px]">{{ o.external_number }}</span> · {{ o.customer_email }}</div>
                        <div class="truncate text-xs" :class="o.error ? 'text-danger' : 'text-subtle'">
                            {{ o.error ?? `${relative(o.received_at)} · forrás: ${sourceLabels[o.utm_source ?? 'direct'] ?? o.utm_source}${o.utm_campaign ? ` / ${o.utm_campaign}` : ''}` }}
                        </div>
                    </div>
                    <RouterLink v-if="o.order_id" :to="`/orders/${o.order_id}`" class="font-mono text-[13px] text-accent hover:underline">{{ o.order_number }}</RouterLink>
                    <StatusBadge :status="o.status" :label="o.status_label" />
                    <span class="num w-24 text-right text-sm font-semibold">{{ o.total ? money(o.total) : '–' }}</span>
                </li>
            </ul>
            <Empty v-else :icon="Store" title="Nincs ilyen webshop-rendelés." />
        </section>

        <!-- Integráció -->
        <section class="card p-4 sm:p-5">
            <h2 class="mb-1 text-[15px] font-semibold">Integráció beállítása</h2>
            <p class="mb-4 text-sm text-muted">A webshop admin felületén ezt a címet add meg webhookként („rendelés létrehozva” eseményre), ugyanazzal a titokkal, mint a <code class="rounded bg-surface-2 px-1 text-[12px]">.env</code>-ben. Titok nélkül az ERP minden kérést elutasít.</p>
            <div class="grid gap-3 lg:grid-cols-2">
                <div v-for="w in report?.webhooks ?? []" :key="w.channel" class="rounded-xl border border-line p-3.5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm font-semibold"><Webhook class="size-4 text-subtle" />{{ w.label }}</span>
                        <Badge :tone="w.configured ? 'success' : 'warning'" dot>{{ w.configured ? 'titok beállítva' : 'nincs titok' }}</Badge>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg bg-surface-2 px-2.5 py-2 font-mono text-[12px]">
                        <span class="min-w-0 flex-1 truncate">POST {{ w.url }}</span>
                        <button class="text-subtle hover:text-fg" :aria-label="`${w.label} webhook-cím másolása`" @click="copy(w.url, w.channel)"><component :is="copied === w.channel ? Check : Copy" class="size-3.5" /></button>
                    </div>
                    <p class="mt-2 flex items-center gap-1.5 text-xs text-subtle"><KeyRound class="size-3.5" />Aláírás: <code class="font-mono">{{ w.header }}</code> · base64(HMAC-SHA256)</p>
                </div>
                <div v-for="f in report?.feeds ?? []" :key="f.url" class="rounded-xl border border-line p-3.5">
                    <div class="mb-2 flex items-center gap-2 text-sm font-semibold"><Rss class="size-4 text-subtle" />{{ f.label }} termékfeed</div>
                    <div class="flex items-center gap-2 rounded-lg bg-surface-2 px-2.5 py-2 font-mono text-[12px]">
                        <span class="min-w-0 flex-1 truncate">{{ f.url }}</span>
                        <button class="text-subtle hover:text-fg" :aria-label="`${f.label} feed címének másolása`" @click="copy(f.url, f.url)"><component :is="copied === f.url ? Check : Copy" class="size-3.5" /></button>
                        <a :href="f.url" target="_blank" rel="noopener" class="text-subtle hover:text-fg" :aria-label="`${f.label} feed megnyitása`"><ExternalLink class="size-3.5" /></a>
                    </div>
                    <p class="mt-2 text-xs text-subtle">Élő készlet és bruttó ár a terméktörzsből, 15 perces gyorsítótárral.</p>
                </div>
            </div>
        </section>
    </div>
</template>
