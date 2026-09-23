<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Copy, Download, FileCode, FileText, Printer, ShieldCheck } from 'lucide-vue-next';
import XmlViewer from './XmlViewer.vue';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import Segmented from '../ui/Segmented.vue';
import StatusBadge from '../ui/StatusBadge.vue';
import { api, apiText } from '../../lib/api.js';
import { isoDate, money, number } from '../../lib/format.js';
import { toast, toastError } from '../../lib/toast.js';

const props = defineProps({ id: [String, Number] });
const emit = defineEmits(['close']);
const inv = ref(null);
const xml = ref('');
const tab = ref('doc');
const copied = ref(false);

watch(
    () => props.id,
    async () => {
        inv.value = null;
        xml.value = '';
        try {
            inv.value = (await api(`/invoicing/invoices/${props.id}`)).data;
        } catch (e) {
            toastError(e);
            emit('close');
        }
    },
    { immediate: true },
);

watch(tab, async (t) => {
    if (t === 'xml' && !xml.value) {
        try {
            xml.value = await apiText(`/invoicing/invoices/${props.id}/xml`);
        } catch (e) {
            toastError(e);
        }
    }
});

const vatPct = computed(() => Math.round((inv.value?.vat_rate ?? 0.27) * 100));
const seller = computed(() => inv.value?.seller ?? {});

const print = () => window.print();

async function copy() {
    try {
        await navigator.clipboard.writeText(xml.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1600);
    } catch {
        toast('A vágólap nem elérhető ebben a böngészőben.', { tone: 'danger' });
    }
}
</script>

<template>
    <Drawer :title="inv?.number ?? 'Számla'" wide @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-2.5">
                <h2 class="font-mono text-base font-semibold">{{ inv?.number ?? '…' }}</h2>
                <StatusBadge v-if="inv" :status="inv.nav_status" :label="inv.nav_status_label" />
            </div>
            <p v-if="inv" class="mt-0.5 text-sm text-subtle">{{ inv.buyer?.name }} · rendelés <RouterLink :to="`/orders/${inv.order_id}`" class="font-mono hover:text-accent">{{ inv.order_number }}</RouterLink></p>
        </template>

        <div v-if="!inv" class="space-y-4"><div class="skeleton h-10 w-60" /><div class="skeleton h-[520px]" /></div>

        <div v-else class="space-y-4">
            <div class="no-print flex flex-wrap items-center justify-between gap-3">
                <Segmented v-model="tab" label="Nézet" :options="[{ value: 'doc', label: 'Számlakép', icon: FileText }, { value: 'xml', label: 'NAV XML', icon: FileCode }]" />
                <div class="flex gap-2">
                    <Button v-if="tab === 'doc'" size="sm" @click="print"><Printer />Nyomtatás</Button>
                    <template v-else>
                        <Button size="sm" :disabled="!xml" @click="copy"><component :is="copied ? Check : Copy" />{{ copied ? 'Másolva' : 'Másolás' }}</Button>
                        <Button size="sm" :href="inv.xml_url" :download="`${inv.number}.xml`"><Download />Letöltés</Button>
                    </template>
                </div>
            </div>

            <div v-if="inv.nav_status === 'invalid'" class="rounded-xl px-4 py-3 text-sm soft-danger" role="alert"><strong>XSD-hiba:</strong> {{ inv.nav_error }}</div>
            <div v-else class="no-print flex items-start gap-3 rounded-xl border border-line px-4 py-3 text-sm">
                <ShieldCheck class="mt-0.5 size-4 shrink-0 text-success" />
                <p class="text-muted"><span class="font-medium text-fg">A NAV hivatalos Online Számla 3.0 XSD-sémája ellen validálva.</span> Beküldésre kész; a beküldés technikai felhasználó megadásával kapcsol be.</p>
            </div>

            <!-- Számlakép: papír, témától függetlenül fehér -->
            <article v-if="tab === 'doc'" class="print-area rounded-xl bg-white p-6 text-[13px] leading-relaxed text-zinc-800 shadow-[0_1px_3px_rgb(0_0_0/0.08),0_0_0_1px_rgb(0_0_0/0.06)] sm:p-9">
                <header class="flex flex-wrap items-start justify-between gap-4 border-b border-zinc-200 pb-5">
                    <div>
                        <div class="text-2xl font-semibold tracking-tight text-zinc-900">Számla</div>
                        <div class="font-mono text-sm text-zinc-500">{{ inv.number }}</div>
                    </div>
                    <div class="text-right text-xs text-zinc-500">
                        <div>Eredeti példány</div>
                        <div>Rendelés: <span class="font-mono">{{ inv.order_number }}</span></div>
                    </div>
                </header>

                <div class="grid gap-6 border-b border-zinc-200 py-5 sm:grid-cols-2">
                    <div>
                        <div class="mb-1 text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Eladó</div>
                        <div class="font-semibold text-zinc-900">{{ seller.name }}</div>
                        <div>{{ seller.postal_code }} {{ seller.city }}, {{ seller.address }}</div>
                        <div class="num">Adószám: {{ seller.tax_number }}</div>
                        <div class="num">Bankszámla: {{ seller.bank_account }}</div>
                    </div>
                    <div>
                        <div class="mb-1 text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Vevő</div>
                        <div class="font-semibold text-zinc-900">{{ inv.buyer?.name }}</div>
                        <div>{{ inv.buyer?.address }}</div>
                        <div v-if="inv.buyer?.tax_number" class="num">Adószám: {{ inv.buyer.tax_number }}</div>
                        <div v-else class="text-zinc-500">Magánszemély (PRIVATE_PERSON)</div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-4 border-b border-zinc-200 py-4 sm:grid-cols-4">
                    <div><dt class="text-[11px] text-zinc-400">Kiállítás</dt><dd class="num font-medium">{{ isoDate(inv.issue_date) }}</dd></div>
                    <div><dt class="text-[11px] text-zinc-400">Teljesítés</dt><dd class="num font-medium">{{ isoDate(inv.delivery_date ?? inv.issue_date) }}</dd></div>
                    <div><dt class="text-[11px] text-zinc-400">Fizetési határidő</dt><dd class="num font-medium">{{ isoDate(inv.payment_due) }}</dd></div>
                    <div><dt class="text-[11px] text-zinc-400">Fizetési mód</dt><dd class="font-medium">Átutalás</dd></div>
                </dl>

                <div class="-mx-2 overflow-x-auto py-4">
                    <table class="w-full min-w-[520px] text-left">
                        <thead class="text-[11px] text-zinc-400">
                            <tr>
                                <th class="px-2 pb-2 font-medium">Megnevezés</th>
                                <th class="px-2 pb-2 text-right font-medium">Menny.</th>
                                <th class="px-2 pb-2 text-right font-medium">Egységár</th>
                                <th class="px-2 pb-2 text-right font-medium">Nettó</th>
                                <th class="px-2 pb-2 text-right font-medium">ÁFA</th>
                                <th class="px-2 pb-2 text-right font-medium">Bruttó</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <tr v-for="l in inv.lines" :key="l.line_number">
                                <td class="px-2 py-2"><div class="font-medium text-zinc-900">{{ l.name }}</div><div class="font-mono text-[11px] text-zinc-400">{{ l.sku }}</div></td>
                                <td class="num px-2 py-2 text-right">{{ l.quantity }} db</td>
                                <td class="num px-2 py-2 text-right">{{ number(l.unit_price) }}</td>
                                <td class="num px-2 py-2 text-right">{{ number(l.net) }}</td>
                                <td class="num px-2 py-2 text-right">{{ number(l.vat) }}</td>
                                <td class="num px-2 py-2 text-right font-medium text-zinc-900">{{ number(l.gross) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col items-end gap-4 border-t border-zinc-200 pt-4 sm:flex-row sm:items-start sm:justify-between">
                    <table class="text-xs text-zinc-500">
                        <thead><tr><th class="pr-5 text-left font-medium">ÁFA-kulcs</th><th class="pr-5 text-right font-medium">Nettó</th><th class="pr-5 text-right font-medium">ÁFA</th><th class="text-right font-medium">Bruttó</th></tr></thead>
                        <tbody><tr class="num"><td class="pr-5">{{ vatPct }}%</td><td class="pr-5 text-right">{{ number(inv.net_total) }}</td><td class="pr-5 text-right">{{ number(inv.vat_total) }}</td><td class="text-right">{{ number(inv.gross_total) }}</td></tr></tbody>
                    </table>
                    <div class="w-full rounded-lg bg-zinc-50 px-4 py-3 text-right sm:w-auto">
                        <div class="text-[11px] text-zinc-500">Fizetendő végösszeg</div>
                        <div class="num text-2xl font-semibold tracking-tight text-zinc-900">{{ money(inv.gross_total) }}</div>
                    </div>
                </div>

                <footer class="mt-8 border-t border-zinc-200 pt-3 text-[11px] text-zinc-400">
                    A számla adatai a NAV Online Számla 3.0 InvoiceData sémájának megfelelő XML-ként is elkészültek. Demó: az eladó és a vevő adatai kitaláltak.
                </footer>
            </article>

            <XmlViewer v-else-if="xml" :xml="xml" />
            <div v-else class="skeleton h-96" />
        </div>
    </Drawer>
</template>
