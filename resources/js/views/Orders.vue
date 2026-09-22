<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api, dateTime, money } from '../api.js';
import Modal from '../components/Modal.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { toast } from '../components/Toast.js';

const props = defineProps({ focus: Object });
const orders = ref([]);
const meta = ref(null);
const filter = ref(props.focus?.status ?? '');
const selected = ref(null);
const busy = ref(false);

const filters = [
    { value: '', label: 'Mind' },
    { value: 'pending', label: 'Függőben' },
    { value: 'paid', label: 'Fizetve' },
    { value: 'shipped', label: 'Kiszállítva' },
    { value: 'cancelled', label: 'Lemondva' },
];

async function load(page = 1) {
    const q = new URLSearchParams({ page, ...(filter.value ? { status: filter.value } : {}) });
    const res = await api(`/orders?${q}`);
    orders.value = res.data;
    meta.value = res.meta;
}

async function open(order) {
    selected.value = (await api(`/orders/${order.id}`)).data;
}

async function transition(status) {
    busy.value = true;
    try {
        selected.value = (await api(`/orders/${selected.value.id}/status`, { method: 'PATCH', body: { status } })).data;
        toast(`${selected.value.number}: ${selected.value.status_label}`);
        load(meta.value?.current_page ?? 1);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

// --- Új rendelés ---
const creating = ref(false);
const customers = ref([]);
const products = ref([]);
const form = reactive({ customer_id: '', lines: [{ product_id: '', quantity: 1 }], note: '' });
const formError = ref('');

async function startCreate() {
    formError.value = '';
    Object.assign(form, { customer_id: '', lines: [{ product_id: '', quantity: 1 }], note: '' });
    const [c, p] = await Promise.all([api('/crm/customers?per_page=100'), api('/inventory/products?per_page=100')]);
    customers.value = c.data;
    products.value = p.data.filter((x) => x.is_active);
    creating.value = true;
}

const productById = (id) => products.value.find((p) => p.id === Number(id));
const total = computed(() => form.lines.reduce((sum, l) => sum + (productById(l.product_id)?.unit_price ?? 0) * (Number(l.quantity) || 0), 0));

async function submit() {
    busy.value = true;
    formError.value = '';
    try {
        const res = await api('/orders', {
            method: 'POST',
            body: {
                customer_id: Number(form.customer_id),
                note: form.note || null,
                items: form.lines.filter((l) => l.product_id).map((l) => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) })),
            },
        });
        creating.value = false;
        toast(`Rendelés rögzítve: ${res.data.number}`);
        filter.value = '';
        await load();
        open(res.data);
    } catch (e) {
        formError.value = Object.values(e.errors).flat()[0] ?? e.message;
    } finally {
        busy.value = false;
    }
}

watch(filter, () => load());
onMounted(() => load());
</script>

<template>
    <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Rendelések</h1>
            <p class="text-sm text-slate-500">Állapotgép: függőben → fizetve → kiszállítva, lemondás a kiszállításig.</p>
        </div>
        <button class="btn-primary" @click="startCreate">+ Új rendelés</button>
    </header>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <button v-for="f in filters" :key="f.value" class="rounded-full px-3 py-1 text-sm transition" :class="filter === f.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100'" @click="filter = f.value">
            {{ f.label }}
        </button>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead class="border-b border-slate-100 bg-slate-50/60">
                <tr><th class="th">Sorszám</th><th class="th">Ügyfél</th><th class="th">Leadva</th><th class="th">Tétel</th><th class="th">Állapot</th><th class="th text-right">Összeg</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="o in orders" :key="o.id" class="cursor-pointer hover:bg-slate-50" tabindex="0" @click="open(o)" @keydown.enter="open(o)">
                    <td class="td font-mono text-xs whitespace-nowrap">{{ o.number }}</td>
                    <td class="td"><div class="font-medium">{{ o.customer.company }}</div><div class="text-xs text-slate-500">{{ o.customer.name }}</div></td>
                    <td class="td text-slate-500">{{ dateTime(o.placed_at) }}</td>
                    <td class="td num text-slate-500">{{ o.items_count }}</td>
                    <td class="td"><StatusBadge :status="o.status" :label="o.status_label" /></td>
                    <td class="td num text-right font-medium whitespace-nowrap">{{ money(o.total) }}</td>
                </tr>
                <tr v-if="!orders.length"><td colspan="6" class="td py-10 text-center text-slate-400">Nincs ilyen rendelés.</td></tr>
            </tbody>
        </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-sm">
        <button class="btn-ghost" :disabled="meta.current_page === 1" @click="load(meta.current_page - 1)">← Előző</button>
        <span class="num text-slate-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn-ghost" :disabled="meta.current_page === meta.last_page" @click="load(meta.current_page + 1)">Következő →</button>
    </div>

    <Modal v-if="selected" :title="selected.number" wide @close="selected = null">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="font-medium">{{ selected.customer.company }}</div>
                <div class="text-sm text-slate-500">{{ selected.customer.name }} · {{ dateTime(selected.placed_at) }}</div>
            </div>
            <StatusBadge :status="selected.status" :label="selected.status_label" />
        </div>
        <table class="mb-4 w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr v-for="i in selected.items" :key="i.product_id">
                    <td class="py-2">{{ i.product_name }}</td>
                    <td class="num py-2 text-right text-slate-500">{{ i.quantity }} × {{ money(i.unit_price) }}</td>
                    <td class="num py-2 text-right font-medium">{{ money(i.line_total) }}</td>
                </tr>
            </tbody>
            <tfoot><tr><td class="pt-3 font-semibold" colspan="2">Végösszeg</td><td class="num pt-3 text-right font-semibold">{{ money(selected.total) }}</td></tr></tfoot>
        </table>
        <div v-if="selected.allowed_transitions.length" class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button
                v-for="t in selected.allowed_transitions"
                :key="t.value"
                :disabled="busy"
                :class="t.value === 'cancelled' ? 'btn-ghost text-red-600' : 'btn-primary'"
                @click="transition(t.value)"
            >
                {{ t.value === 'cancelled' ? 'Lemondás (készlet visszakerül)' : `Jelölés: ${t.label}` }}
            </button>
        </div>
        <p v-else class="border-t border-slate-100 pt-4 text-sm text-slate-500">Lezárt rendelés, további állapotváltás nem lehetséges.</p>
    </Modal>

    <Modal v-if="creating" title="Új rendelés" wide @close="creating = false">
        <form class="space-y-4" @submit.prevent="submit">
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Ügyfél</span>
                <select v-model="form.customer_id" class="input" required>
                    <option value="" disabled>Válassz ügyfelet…</option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.company }} – {{ c.name }}</option>
                </select>
            </label>
            <div class="space-y-2">
                <span class="block text-sm font-medium">Tételek</span>
                <div v-for="(line, idx) in form.lines" :key="idx" class="flex gap-2">
                    <select v-model="line.product_id" class="input flex-1" required :aria-label="`${idx + 1}. termék`">
                        <option value="" disabled>Termék…</option>
                        <option v-for="p in products" :key="p.id" :value="p.id" :disabled="p.stock === 0">{{ p.name }} · {{ money(p.unit_price) }} · {{ p.stock }} db</option>
                    </select>
                    <input v-model.number="line.quantity" type="number" min="1" class="input w-20" :aria-label="`${idx + 1}. mennyiség`" required />
                    <button v-if="form.lines.length > 1" type="button" class="btn-ghost px-2.5" aria-label="Tétel törlése" @click="form.lines.splice(idx, 1)">✕</button>
                </div>
                <button type="button" class="text-sm font-medium text-brand-600" @click="form.lines.push({ product_id: '', quantity: 1 })">+ Tétel hozzáadása</button>
            </div>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Megjegyzés</span>
                <input v-model="form.note" class="input" placeholder="pl. szállítás a telephelyre" />
            </label>
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
            <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                <div class="text-sm text-slate-500">Összesen: <span class="num text-base font-semibold text-slate-900">{{ money(total) }}</span></div>
                <button class="btn-primary" :disabled="busy">Rendelés rögzítése</button>
            </div>
        </form>
    </Modal>
</template>
