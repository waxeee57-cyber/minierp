<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api, dateTime, money } from '../api.js';
import Modal from '../components/Modal.vue';
import { toast } from '../components/Toast.js';

const props = defineProps({ focus: Object });
const products = ref([]);
const forecast = ref({});
const lowOnly = ref(!!props.focus?.lowStock);
const search = ref('');
const selected = ref(null);
const move = reactive({ reason: 'purchase', quantity: 10, reference: '', note: '' });
const moveError = ref('');
const busy = ref(false);

async function load() {
    const q = new URLSearchParams({ per_page: 100, ...(lowOnly.value ? { low_stock: 1 } : {}), ...(search.value ? { search: search.value } : {}) });
    const [p, f] = await Promise.all([api(`/inventory/products?${q}`), api('/inventory/forecast')]);
    products.value = p.data;
    forecast.value = Object.fromEntries(f.data.map((x) => [x.product_id, x]));
}

async function open(p) {
    Object.assign(move, { reason: 'purchase', quantity: 10, reference: '', note: '' });
    moveError.value = '';
    selected.value = (await api(`/inventory/products/${p.id}`)).data;
}

async function record() {
    busy.value = true;
    moveError.value = '';
    try {
        await api(`/inventory/products/${selected.value.id}/movements`, { method: 'POST', body: { ...move, quantity: Number(move.quantity) } });
        toast(move.reason === 'purchase' ? 'Bevételezve' : 'Korrekció rögzítve');
        await open(selected.value);
        load();
    } catch (e) {
        moveError.value = Object.values(e.errors).flat()[0] ?? e.message;
    } finally {
        busy.value = false;
    }
}

let t;
watch(search, () => { clearTimeout(t); t = setTimeout(load, 250); });
watch(lowOnly, load);
onMounted(load);

const fill = (p) => Math.min(100, (p.stock / Math.max(p.reorder_level * 4, 1)) * 100);
</script>

<template>
    <header class="mb-5">
        <h1 class="text-xl font-semibold">Készlet</h1>
        <p class="text-sm text-slate-500">Minden változás naplózva. A „Kitart” az elmúlt 30 nap eladásaiból számolt előrejelzés.</p>
    </header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input v-model="search" type="search" class="input max-w-xs" placeholder="Keresés név vagy cikkszám szerint" aria-label="Termék keresése" />
        <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="lowOnly" type="checkbox" class="rounded" /> Csak alacsony készlet</label>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[820px]">
            <thead class="border-b border-slate-100 bg-slate-50/60">
                <tr><th class="th">Cikkszám</th><th class="th">Termék</th><th class="th text-right">Nettó ár</th><th class="th w-56">Készlet</th><th class="th text-right">Kitart</th><th class="th text-right">Javasolt rendelés</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="p in products" :key="p.id" class="cursor-pointer hover:bg-slate-50" tabindex="0" @click="open(p)" @keydown.enter="open(p)">
                    <td class="td font-mono text-xs text-slate-500">{{ p.sku }}</td>
                    <td class="td font-medium">{{ p.name }}</td>
                    <td class="td num text-right">{{ money(p.unit_price) }}</td>
                    <td class="td">
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 flex-1 rounded-full bg-slate-100">
                                <div class="h-full rounded-full" :class="p.stock === 0 ? 'bg-red-500' : p.is_low_stock ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: `${Math.max(fill(p), p.stock ? 4 : 0)}%` }" />
                            </div>
                            <span class="num w-16 text-right text-sm" :class="p.stock === 0 ? 'font-semibold text-red-600' : p.is_low_stock ? 'font-semibold text-amber-600' : ''">{{ p.stock }} db</span>
                        </div>
                    </td>
                    <td class="td num text-right whitespace-nowrap" :title="forecast[p.id]?.stockout_on ? `Várható kifogyás: ${forecast[p.id].stockout_on}` : 'Nincs eladás az időszakban'">
                        <span v-if="forecast[p.id]?.days_of_cover === null || !forecast[p.id]" class="text-slate-400">–</span>
                        <span v-else :class="forecast[p.id].days_of_cover <= 7 ? 'font-semibold text-amber-600' : 'text-slate-600'">{{ forecast[p.id].days_of_cover }} nap</span>
                    </td>
                    <td class="td num text-right">
                        <span v-if="forecast[p.id]?.suggested_reorder" class="rounded bg-brand-50 px-1.5 py-0.5 font-medium text-brand-700">{{ forecast[p.id].suggested_reorder }} db</span>
                        <span v-else class="text-slate-300">–</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Modal v-if="selected" :title="selected.name" wide @close="selected = null">
        <div class="mb-5 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Készlet</div><div class="num text-lg font-semibold">{{ selected.stock }} db</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Újrarendelési szint</div><div class="num text-lg font-semibold">{{ selected.reorder_level }} db</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Egységár</div><div class="num text-lg font-semibold">{{ money(selected.unit_price) }}</div></div>
        </div>

        <form class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4" @submit.prevent="record">
            <select v-model="move.reason" class="input" aria-label="Mozgás típusa">
                <option value="purchase">Bevételezés</option>
                <option value="adjustment">Korrekció (±)</option>
            </select>
            <input v-model.number="move.quantity" type="number" class="input" aria-label="Mennyiség" required />
            <input v-model="move.reference" class="input" placeholder="Bizonylat" aria-label="Bizonylatszám" />
            <button class="btn-primary" :disabled="busy">Rögzítés</button>
            <p v-if="moveError" class="col-span-full rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ moveError }}</p>
        </form>

        <h3 class="mb-2 text-sm font-semibold">Utolsó mozgások</h3>
        <ul class="divide-y divide-slate-100 text-sm">
            <li v-for="m in selected.movements" :key="m.id" class="flex items-center justify-between py-2">
                <span><span class="font-medium">{{ m.reason_label }}</span> <span class="text-slate-500">{{ m.reference }}</span></span>
                <span class="flex items-center gap-4">
                    <span class="text-xs text-slate-400">{{ dateTime(m.created_at) }}</span>
                    <span class="num w-14 text-right font-medium" :class="m.quantity > 0 ? 'text-emerald-600' : 'text-slate-700'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</span>
                </span>
            </li>
        </ul>
    </Modal>
</template>
