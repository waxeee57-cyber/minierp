<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ArrowDownToLine, PackagePlus, Pencil, ShoppingCart, SlidersHorizontal, Sparkles, Undo2 } from 'lucide-vue-next';
import AreaChart from '../ui/AreaChart.vue';
import Badge from '../ui/Badge.vue';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import Segmented from '../ui/Segmented.vue';
import { api } from '../../lib/api.js';
import { refreshBadges } from '../../lib/badges.js';
import { date, decimal, isoDate, money, number, relative } from '../../lib/format.js';
import { toast, toastError } from '../../lib/toast.js';

const props = defineProps({ id: [String, Number], forecast: Object, meta: Object });
const emit = defineEmits(['close', 'changed']);
const product = ref(null);
const busy = ref(false);
const error = ref('');
const move = reactive({ reason: 'purchase', quantity: 10, reference: '', note: '' });
const editing = ref(false);
const edit = reactive({});
const editError = ref('');
const qtyInput = ref(null);

async function load() {
    try {
        product.value = (await api(`/inventory/products/${props.id}`)).data;
    } catch (e) {
        toastError(e);
        emit('close');
    }
}
watch(() => props.id, () => ((product.value = null), (editing.value = false), load()), { immediate: true });

const f = computed(() => props.forecast);
const history = computed(() => {
    const ms = [...(product.value?.movements ?? [])].reverse();
    const pts = ms.map((m) => ({ date: m.created_at, stock: m.balance_after }));
    // A lépcső a mai napig tart: a jelenlegi készlet is látszik.
    return pts.length ? [...pts, { date: new Date().toISOString(), stock: product.value.stock }] : pts;
});
const reasonIcon = { purchase: ArrowDownToLine, sale: ShoppingCart, return: Undo2, adjustment: SlidersHorizontal };

function prefill(qty) {
    Object.assign(move, { reason: 'purchase', quantity: qty, reference: '', note: 'Előrejelzés alapján' });
    qtyInput.value?.focus();
}

async function record() {
    busy.value = true;
    error.value = '';
    try {
        await api(`/inventory/products/${product.value.id}/movements`, { method: 'POST', body: { ...move, quantity: Number(move.quantity) } });
        toast(move.reason === 'purchase' ? `Bevételezve: ${move.quantity} db` : `Korrekció rögzítve: ${move.quantity > 0 ? '+' : ''}${move.quantity} db`, { description: product.value.name });
        Object.assign(move, { quantity: 10, reference: '', note: '' });
        await load();
        emit('changed');
        refreshBadges();
    } catch (e) {
        error.value = e.first;
    } finally {
        busy.value = false;
    }
}

function startEdit() {
    const p = product.value;
    Object.assign(edit, { sku: p.sku, name: p.name, unit_price: p.unit_price, reorder_level: p.reorder_level, description: p.description ?? '', is_active: p.is_active });
    editError.value = '';
    editing.value = true;
}

async function save() {
    busy.value = true;
    editError.value = '';
    try {
        await api(`/inventory/products/${product.value.id}`, { method: 'PATCH', body: { ...edit, description: edit.description || null } });
        toast('Termékadatok mentve');
        editing.value = false;
        await load();
        emit('changed');
        refreshBadges();
    } catch (e) {
        editError.value = e.first;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Drawer :title="product?.name ?? 'Termék'" wide @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="truncate text-base font-semibold tracking-tight">{{ product?.name ?? '…' }}</h2>
                <Badge v-if="product && !product.is_active">inaktív</Badge>
            </div>
            <p v-if="product" class="mt-0.5 text-sm text-subtle"><span class="font-mono">{{ product.sku }}</span> · {{ money(product.unit_price) }} nettó</p>
        </template>
        <template #actions>
            <Button v-if="product && !editing" size="sm" variant="ghost" @click="startEdit"><Pencil />Szerkesztés</Button>
        </template>

        <div v-if="!product" class="space-y-4"><div class="skeleton h-20" /><div class="skeleton h-48" /></div>

        <div v-else-if="editing" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="p-name">Megnevezés</label><input id="p-name" v-model="edit.name" class="field" autofocus /></div>
                <div><label class="label" for="p-sku">Cikkszám</label><input id="p-sku" v-model="edit.sku" class="field font-mono" /></div>
                <div><label class="label" for="p-price">Nettó egységár (Ft)</label><input id="p-price" v-model.number="edit.unit_price" type="number" min="0" class="field num" /></div>
                <div><label class="label" for="p-reorder">Újrarendelési szint (db)</label><input id="p-reorder" v-model.number="edit.reorder_level" type="number" min="0" class="field num" /></div>
            </div>
            <div><label class="label" for="p-desc">Leírás</label><textarea id="p-desc" v-model="edit.description" rows="3" class="field" /></div>
            <label class="flex items-center gap-2.5 text-sm"><input v-model="edit.is_active" type="checkbox" class="size-4 accent-[var(--accent)]" />Aktív – rendelhető</label>
            <p class="text-xs text-subtle">A készlet itt nem írható át: csak naplózott mozgással (bevételezés, korrekció) változhat.</p>
            <p v-if="editError" class="rounded-lg px-3 py-2 text-sm soft-danger" role="alert">{{ editError }}</p>
        </div>

        <div v-else class="space-y-6">
            <div class="grid grid-cols-3 gap-2.5">
                <div class="rounded-xl border border-line p-3">
                    <div class="text-xs text-subtle">Készlet</div>
                    <div class="num mt-1 text-xl font-semibold" :class="product.stock === 0 ? 'text-danger' : product.is_low_stock ? 'text-warning' : ''">{{ number(product.stock) }} <span class="text-sm font-normal text-subtle">db</span></div>
                    <div class="text-xs text-subtle">szint: {{ product.reorder_level }} db</div>
                </div>
                <div class="rounded-xl border border-line p-3">
                    <div class="text-xs text-subtle">Kitart</div>
                    <div class="num mt-1 text-xl font-semibold" :class="f?.days_of_cover === 0 ? 'text-danger' : f?.days_of_cover <= (meta?.lead_time_days ?? 7) ? 'text-warning' : ''">{{ f?.days_of_cover === null || !f ? '–' : f.days_of_cover === 0 ? '0' : f.days_of_cover }} <span v-if="f?.days_of_cover !== null && f" class="text-sm font-normal text-subtle">nap</span></div>
                    <div class="text-xs text-subtle">{{ f?.stockout_on ? `kifogy: ${date(f.stockout_on)}` : 'nincs eladás' }}</div>
                </div>
                <div class="rounded-xl border border-line p-3">
                    <div class="text-xs text-subtle">Napi kereslet</div>
                    <div class="num mt-1 text-xl font-semibold">{{ decimal(f?.daily_demand ?? 0) }} <span class="text-sm font-normal text-subtle">db</span></div>
                    <div class="text-xs text-subtle">{{ meta?.window_days ?? 30 }} napos átlag</div>
                </div>
            </div>

            <div v-if="f?.suggested_reorder" class="flex flex-col gap-3 rounded-xl border border-line bg-[color-mix(in_oklab,var(--accent)_6%,var(--surface))] p-4 sm:flex-row sm:items-center">
                <Sparkles class="size-5 shrink-0 text-accent" />
                <p class="flex-1 text-sm">
                    <span class="font-semibold">Rendelj {{ f.suggested_reorder }} db-ot.</span>
                    <span class="text-muted"> Így a {{ meta.lead_time_days }} napos beszerzési átfutás és {{ meta.safety_days }} nap biztonsági készlet is fedezve van.</span>
                </p>
                <Button size="sm" variant="primary" @click="prefill(f.suggested_reorder)"><PackagePlus />Bevételezés előkészítése</Button>
            </div>

            <section v-if="history.length > 1">
                <h3 class="mb-2 text-[13px] font-semibold text-muted">Készlet alakulása</h3>
                <AreaChart :points="history" :series="[{ key: 'stock', label: 'Készlet', tone: 'accent' }]" :height="170" step time-scale :format="(v) => `${Math.round(v)} db`" :format-x="date" />
            </section>

            <section>
                <h3 class="mb-2 text-[13px] font-semibold text-muted">Készletmozgás rögzítése</h3>
                <form class="space-y-3 rounded-xl border border-line p-3.5" @submit.prevent="record">
                    <Segmented v-model="move.reason" label="Mozgás típusa" :options="[{ value: 'purchase', label: 'Bevételezés', icon: ArrowDownToLine }, { value: 'adjustment', label: 'Korrekció ±', icon: SlidersHorizontal }]" />
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[7rem_1fr_1fr_auto]">
                        <input ref="qtyInput" v-model.number="move.quantity" type="number" class="field num" aria-label="Mennyiség" required />
                        <input v-model="move.reference" class="field" placeholder="Bizonylatszám" aria-label="Bizonylatszám" maxlength="60" />
                        <input v-model="move.note" class="field col-span-2 sm:col-span-1" placeholder="Megjegyzés" aria-label="Megjegyzés" maxlength="255" />
                        <Button type="submit" variant="primary" class="col-span-2 sm:col-span-1" :loading="busy">Rögzítés</Button>
                    </div>
                    <p class="text-xs text-subtle">{{ move.reason === 'purchase' ? 'Pozitív mennyiség, a készlet nő.' : 'Negatív érték csökkenti (pl. selejt), a készlet nem mehet 0 alá.' }}</p>
                    <p v-if="error" class="rounded-lg px-3 py-2 text-sm soft-danger" role="alert">{{ error }}</p>
                </form>
            </section>

            <section>
                <h3 class="mb-2 text-[13px] font-semibold text-muted">Mozgásnapló <span class="font-normal text-subtle">· változtathatatlan</span></h3>
                <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line">
                    <li v-for="m in product.movements" :key="m.id" class="flex items-center gap-3 px-3.5 py-2.5">
                        <div class="grid size-8 shrink-0 place-items-center rounded-lg" :class="m.quantity > 0 ? 'soft-success' : 'soft-neutral'"><component :is="reasonIcon[m.reason] ?? SlidersHorizontal" class="size-4" /></div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ m.reason_label }} <span v-if="m.reference" class="font-mono text-xs font-normal text-subtle">{{ m.reference }}</span></div>
                            <div class="truncate text-xs text-subtle">{{ relative(m.created_at) }}<template v-if="m.note"> · {{ m.note }}</template></div>
                        </div>
                        <div class="text-right">
                            <div class="num text-sm font-semibold" :class="m.quantity > 0 ? 'text-success' : ''">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</div>
                            <div class="num text-xs text-subtle">→ {{ m.balance_after }} db</div>
                        </div>
                    </li>
                </ul>
                <p class="mt-2 text-xs text-subtle">Legutóbbi {{ product.movements.length }} mozgás · {{ isoDate(new Date()) }}</p>
            </section>
        </div>

        <template v-if="editing" #footer>
            <div class="flex-1" />
            <Button variant="ghost" @click="editing = false">Mégse</Button>
            <Button variant="primary" :loading="busy" @click="save">Mentés</Button>
        </template>
    </Drawer>
</template>
