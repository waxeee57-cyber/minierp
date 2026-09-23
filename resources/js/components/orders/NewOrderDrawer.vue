<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Minus, Plus, TriangleAlert, X } from 'lucide-vue-next';
import Button from '../ui/Button.vue';
import Combobox from '../ui/Combobox.vue';
import Drawer from '../ui/Drawer.vue';
import Kbd from '../ui/Kbd.vue';
import { api } from '../../lib/api.js';
import { refreshBadges } from '../../lib/badges.js';
import { money } from '../../lib/format.js';
import { toast } from '../../lib/toast.js';

const props = defineProps({ customerId: [String, Number] });
const emit = defineEmits(['close', 'created']);
const customers = ref([]);
const products = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const form = reactive({ customer_id: props.customerId ? Number(props.customerId) : null, lines: [{ product_id: null, quantity: 1 }], note: '' });

onMounted(async () => {
    try {
        const [c, p] = await Promise.all([api('/crm/customers?per_page=100'), api('/inventory/products?per_page=100')]);
        customers.value = c.data;
        products.value = p.data.filter((x) => x.is_active);
    } catch (e) {
        error.value = e.first;
    } finally {
        loading.value = false;
    }
});

const customerOptions = computed(() => customers.value.map((c) => ({ value: c.id, label: c.company ?? c.name, sub: `${c.name} · ${c.city ?? ''}` })));
const productOptions = computed(() =>
    products.value.map((p) => ({ value: p.id, label: p.name, sub: `${p.sku} · ${money(p.unit_price)}`, meta: p.stock ? `${p.stock} db` : 'elfogyott', disabled: p.stock === 0 })),
);
const product = (id) => products.value.find((p) => p.id === id);

// Ugyanaz a termék több soron: a készletellenőrzés az összesített mennyiségre fut, mint a szerveren.
const requested = computed(() => form.lines.reduce((m, l) => (l.product_id ? m.set(l.product_id, (m.get(l.product_id) ?? 0) + (Number(l.quantity) || 0)) : m), new Map()));
const shortage = (l) => {
    const p = product(l.product_id);
    return p && requested.value.get(p.id) > p.stock ? p.stock : null;
};

const net = computed(() => form.lines.reduce((s, l) => s + (product(l.product_id)?.unit_price ?? 0) * (Number(l.quantity) || 0), 0));
const vat = computed(() => Math.round(net.value * 0.27));
const valid = computed(() => form.customer_id && form.lines.some((l) => l.product_id) && form.lines.every((l) => !l.product_id || (l.quantity >= 1 && shortage(l) === null)));

function bump(l, d) {
    l.quantity = Math.max(1, (Number(l.quantity) || 1) + d);
}

async function submit() {
    if (!valid.value || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const res = await api('/orders', {
            method: 'POST',
            body: {
                customer_id: form.customer_id,
                note: form.note || null,
                items: form.lines.filter((l) => l.product_id).map((l) => ({ product_id: l.product_id, quantity: Number(l.quantity) })),
            },
        });
        toast(`Rendelés rögzítve: ${res.data.number}`, { description: 'A készlet lefoglalva, az ügyfél idővonala frissült.' });
        refreshBadges();
        emit('created', res.data);
    } catch (e) {
        error.value = e.first;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Drawer title="Új rendelés" subtitle="A készletfoglalás egy tranzakcióban, soronkénti zárolással történik." @close="emit('close')">
        <div v-if="loading" class="space-y-4"><div class="skeleton h-10" /><div class="skeleton h-28" /></div>
        <form v-else id="new-order" class="space-y-6" @submit.prevent="submit" @keydown.meta.enter.prevent="submit" @keydown.ctrl.enter.prevent="submit">
            <div>
                <span class="label">Ügyfél</span>
                <Combobox v-model="form.customer_id" :options="customerOptions" label="Ügyfél" placeholder="Keress cégnévre vagy kapcsolattartóra…" />
            </div>

            <div>
                <span class="label">Tételek</span>
                <div class="space-y-2.5">
                    <div v-for="(l, idx) in form.lines" :key="idx" class="rounded-xl border border-line p-3">
                        <div class="flex gap-2">
                            <Combobox v-model="l.product_id" class="min-w-0 flex-1" :options="productOptions" :label="`${idx + 1}. termék`" placeholder="Termék…" :invalid="shortage(l) !== null" />
                            <button v-if="form.lines.length > 1" type="button" class="grid size-[38px] shrink-0 place-items-center rounded-[10px] text-subtle hover:bg-surface-2 hover:text-danger" :aria-label="`${idx + 1}. tétel törlése`" @click="form.lines.splice(idx, 1)"><X class="size-4" /></button>
                        </div>
                        <div class="mt-2.5 flex items-center gap-3">
                            <div class="flex items-center rounded-[10px] border border-line-strong">
                                <button type="button" class="grid size-9 place-items-center text-muted hover:text-fg" aria-label="Kevesebb" @click="bump(l, -1)"><Minus class="size-3.5" /></button>
                                <input v-model.number="l.quantity" type="number" min="1" inputmode="numeric" class="num h-9 w-12 bg-transparent text-center text-sm font-medium outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" :aria-label="`${idx + 1}. mennyiség`" />
                                <button type="button" class="grid size-9 place-items-center text-muted hover:text-fg" aria-label="Több" @click="bump(l, 1)"><Plus class="size-3.5" /></button>
                            </div>
                            <span v-if="shortage(l) !== null" class="flex items-center gap-1 text-xs font-medium text-danger"><TriangleAlert class="size-3.5" />Csak {{ shortage(l) }} db van készleten</span>
                            <span class="num ml-auto text-sm font-semibold">{{ money((product(l.product_id)?.unit_price ?? 0) * (Number(l.quantity) || 0)) }}</span>
                        </div>
                    </div>
                </div>
                <Button class="mt-2.5" size="sm" variant="ghost" @click="form.lines.push({ product_id: null, quantity: 1 })"><Plus />Tétel hozzáadása</Button>
            </div>

            <div>
                <label class="label" for="order-note">Megjegyzés</label>
                <textarea id="order-note" v-model="form.note" rows="2" class="field resize-none" placeholder="pl. szállítás a telephelyre, 8–16 óra között" maxlength="1000" />
            </div>

            <dl class="space-y-1.5 rounded-xl bg-surface-2 p-4 text-sm">
                <div class="flex justify-between text-muted"><dt>Nettó</dt><dd class="num">{{ money(net) }}</dd></div>
                <div class="flex justify-between text-muted"><dt>ÁFA 27%</dt><dd class="num">{{ money(vat) }}</dd></div>
                <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Bruttó végösszeg</dt><dd class="num">{{ money(net + vat) }}</dd></div>
            </dl>

            <p v-if="error" class="flex items-start gap-2 rounded-lg px-3 py-2.5 text-sm soft-danger" role="alert"><TriangleAlert class="mt-0.5 size-4 shrink-0" />{{ error }}</p>
        </form>

        <template #footer>
            <span class="hidden items-center gap-1 text-xs text-subtle sm:flex"><Kbd>⌘</Kbd><Kbd>↵</Kbd> rögzítés</span>
            <div class="flex-1" />
            <Button variant="ghost" @click="emit('close')">Mégse</Button>
            <Button variant="primary" type="submit" form="new-order" :loading="busy" :disabled="!valid">Rendelés rögzítése</Button>
        </template>
    </Drawer>
</template>
