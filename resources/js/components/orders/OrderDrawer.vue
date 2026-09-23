<script setup>
import { computed, ref, watch } from 'vue';
import { Ban, Check, ChevronRight, FileText, Store, Truck, Wallet } from 'lucide-vue-next';
import Avatar from '../ui/Avatar.vue';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import StatusBadge from '../ui/StatusBadge.vue';
import { api } from '../../lib/api.js';
import { refreshBadges } from '../../lib/badges.js';
import { dateTime, money } from '../../lib/format.js';
import { toast, toastError } from '../../lib/toast.js';

const props = defineProps({ id: [String, Number] });
const emit = defineEmits(['close', 'changed']);
const order = ref(null);
const invoice = ref(null);
const source = ref(null);
const busy = ref(null);
const confirmCancel = ref(false);

async function load() {
    try {
        const [o, inv, ch] = await Promise.all([
            api(`/orders/${props.id}`),
            api(`/invoicing/invoices?order_id=${props.id}`),
            api(`/channels/orders?order_id=${props.id}`),
        ]);
        order.value = o.data;
        invoice.value = inv.data[0] ?? null;
        source.value = ch.data[0] ?? null;
    } catch (e) {
        toastError(e);
        emit('close');
    }
}
watch(() => props.id, () => ((order.value = null), (confirmCancel.value = false), load()), { immediate: true });

const steps = computed(() => {
    const o = order.value;
    if (!o) return [];
    const base = [
        { key: 'placed', label: 'Leadva', at: o.placed_at },
        { key: 'paid', label: 'Fizetve', at: o.paid_at },
        { key: 'shipped', label: 'Kiszállítva', at: o.shipped_at },
    ];
    if (o.status !== 'cancelled') return base;
    return [...base.filter((s) => s.at), { key: 'cancelled', label: 'Lemondva', at: o.cancelled_at, cancelled: true }];
});

const vat = computed(() => Math.round((order.value?.total ?? 0) * 0.27));

async function transition(status) {
    if (status === 'cancelled' && !confirmCancel.value) return (confirmCancel.value = true);
    busy.value = status;
    try {
        const updated = (await api(`/orders/${order.value.id}/status`, { method: 'PATCH', body: { status } })).data;
        await load();
        const msg = {
            paid: [`${updated.number} fizetve`, invoice.value ? `Számla kiállítva: ${invoice.value.number} · NAV XSD-validálva` : null],
            shipped: [`${updated.number} kiszállítva`, null],
            cancelled: [`${updated.number} lemondva`, 'A lefoglalt készlet visszakerült a raktárba.'],
        }[status];
        toast(msg[0], { description: msg[1] });
        emit('changed');
        refreshBadges();
    } catch (e) {
        toastError(e);
    } finally {
        busy.value = null;
        confirmCancel.value = false;
    }
}
</script>

<template>
    <Drawer :title="order?.number ?? 'Rendelés'" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-2.5">
                <h2 class="font-mono text-base font-semibold tracking-tight">{{ order?.number ?? '…' }}</h2>
                <StatusBadge v-if="order" :status="order.status" :label="order.status_label" />
            </div>
            <p v-if="order" class="mt-0.5 text-sm text-subtle">
                Leadva {{ dateTime(order.placed_at) }}
                <RouterLink v-if="source" to="/channels" class="ml-1 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium soft-accent hover:underline">
                    <Store class="size-3" />{{ source.channel_label }} {{ source.external_number }}<template v-if="source.utm_source"> · {{ source.utm_source }}</template>
                </RouterLink>
            </p>
        </template>

        <div v-if="!order" class="space-y-4"><div class="skeleton h-16" /><div class="skeleton h-40" /><div class="skeleton h-24" /></div>

        <div v-else class="space-y-6">
            <!-- Életút -->
            <ol class="flex items-start">
                <li v-for="(s, i) in steps" :key="s.key" class="relative flex flex-1 flex-col items-center text-center">
                    <div v-if="i" class="absolute top-3.5 right-1/2 h-0.5 w-full -translate-y-1/2" :class="s.at ? (s.cancelled ? 'bg-danger/50' : 'bg-accent') : 'bg-line'" />
                    <div class="relative z-10 grid size-7 place-items-center rounded-full border-2 text-xs" :class="s.cancelled ? 'border-danger bg-danger text-white' : s.at ? 'border-accent bg-accent text-accent-fg' : 'border-line-strong bg-surface text-subtle'">
                        <Ban v-if="s.cancelled" class="size-3.5" /><Check v-else-if="s.at" class="size-3.5" /><span v-else>{{ i + 1 }}</span>
                    </div>
                    <div class="mt-2 text-[13px] font-medium" :class="s.at ? '' : 'text-subtle'">{{ s.label }}</div>
                    <div class="text-xs text-subtle">{{ s.at ? dateTime(s.at) : '–' }}</div>
                </li>
            </ol>

            <!-- Ügyfél -->
            <RouterLink :to="`/customers/${order.customer.id}`" class="flex items-center gap-3 rounded-xl border border-line p-3 transition hover:border-line-strong hover:bg-surface-2/50">
                <Avatar :name="order.customer.company ?? order.customer.name" />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ order.customer.company ?? order.customer.name }}</div>
                    <div class="truncate text-xs text-subtle">{{ order.customer.company ? order.customer.name : 'Magánszemély' }}</div>
                </div>
                <ChevronRight class="size-4 text-subtle" />
            </RouterLink>

            <!-- Tételek -->
            <section>
                <h3 class="mb-2 text-[13px] font-semibold text-muted">Tételek</h3>
                <div class="overflow-hidden rounded-xl border border-line">
                    <div v-for="i in order.items" :key="i.product_id" class="flex items-center gap-3 border-b border-line px-3.5 py-3 last:border-b-0">
                        <div class="min-w-0 flex-1">
                            <RouterLink :to="`/inventory/${i.product_id}`" class="block truncate text-sm font-medium hover:text-accent">{{ i.product_name }}</RouterLink>
                            <div class="num text-xs text-subtle">{{ i.quantity }} db × {{ money(i.unit_price) }}</div>
                        </div>
                        <div class="num text-sm font-semibold">{{ money(i.line_total) }}</div>
                    </div>
                    <dl class="space-y-1.5 bg-surface-2/60 px-3.5 py-3 text-sm">
                        <div class="flex justify-between text-muted"><dt>Nettó</dt><dd class="num">{{ money(order.total) }}</dd></div>
                        <div class="flex justify-between text-muted"><dt>ÁFA 27%</dt><dd class="num">{{ money(invoice?.vat_total ?? vat) }}</dd></div>
                        <div class="flex justify-between text-base font-semibold"><dt>Bruttó</dt><dd class="num">{{ money(invoice?.gross_total ?? order.total + vat) }}</dd></div>
                    </dl>
                </div>
                <p v-if="order.note" class="mt-3 rounded-lg bg-surface-2 px-3 py-2 text-sm text-muted">„{{ order.note }}”</p>
            </section>

            <!-- Számla -->
            <RouterLink v-if="invoice" :to="`/invoices/${invoice.id}`" class="group flex items-center gap-3 rounded-xl border border-line p-3.5 transition hover:border-line-strong hover:bg-surface-2/50">
                <div class="grid size-10 place-items-center rounded-lg soft-accent"><FileText class="size-5" /></div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs text-subtle">Számla · NAV Online Számla 3.0<span class="sm:hidden"> · {{ invoice.nav_status_label }}</span></div>
                    <div class="font-mono text-sm font-semibold">{{ invoice.number }}</div>
                </div>
                <StatusBadge class="max-sm:hidden" :status="invoice.nav_status" :label="invoice.nav_status_label" />
                <ChevronRight class="size-4 text-subtle" />
            </RouterLink>
            <p v-else-if="order.status === 'pending'" class="flex items-center gap-2 rounded-xl border border-dashed border-line-strong px-3.5 py-3 text-sm text-subtle">
                <FileText class="size-4" />A számla fizetéskor automatikusan elkészül, NAV XSD-validálással.
            </p>
        </div>

        <template v-if="order" #footer>
            <template v-if="order.allowed_transitions.length">
                <Button v-if="order.allowed_transitions.some((t) => t.value === 'cancelled')" :variant="confirmCancel ? 'danger' : 'ghost'" :loading="busy === 'cancelled'" :disabled="!!busy" @click="transition('cancelled')" @blur="confirmCancel = false">
                    <Ban />{{ confirmCancel ? 'Biztosan? Készlet visszakerül' : 'Lemondás' }}
                </Button>
                <div class="flex-1" />
                <Button v-if="order.allowed_transitions.some((t) => t.value === 'paid')" variant="primary" :loading="busy === 'paid'" :disabled="!!busy" @click="transition('paid')"><Wallet />Fizetve · számla kiállítása</Button>
                <Button v-if="order.allowed_transitions.some((t) => t.value === 'shipped')" variant="primary" :loading="busy === 'shipped'" :disabled="!!busy" @click="transition('shipped')"><Truck />Kiszállítva</Button>
            </template>
            <p v-else class="text-sm text-subtle">Lezárt rendelés – további állapotváltás nem lehetséges.</p>
        </template>
    </Drawer>
</template>
