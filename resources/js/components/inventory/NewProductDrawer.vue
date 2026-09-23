<script setup>
import { reactive, ref } from 'vue';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import { api } from '../../lib/api.js';
import { toast } from '../../lib/toast.js';

const emit = defineEmits(['close', 'created']);
const form = reactive({ sku: '', name: '', unit_price: null, reorder_level: 5, description: '' });
const errors = ref({});
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    try {
        const res = await api('/inventory/products', { method: 'POST', body: { ...form, sku: form.sku.trim().toUpperCase(), description: form.description || null, is_active: true } });
        toast(`Termék létrehozva: ${res.data.name}`, { description: 'Nulla készlettel indul – rögzíts bevételezést.' });
        emit('created', res.data);
    } catch (e) {
        errors.value = e.errors;
        if (!Object.keys(e.errors).length) errors.value = { _: [e.message] };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Drawer title="Új termék" subtitle="A készlet csak naplózott bevételezéssel keletkezhet, ezért nulláról indul." @close="emit('close')">
        <form id="new-product" class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="np-name">Megnevezés</label>
                <input id="np-name" v-model="form.name" class="field" placeholder="pl. Logitech Brio 4K webkamera" autofocus required maxlength="160" />
                <p v-if="errors.name" class="mt-1 text-xs text-danger">{{ errors.name[0] }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="np-sku">Cikkszám</label>
                    <input id="np-sku" v-model="form.sku" class="field font-mono uppercase" placeholder="IT-1013" required maxlength="40" />
                    <p v-if="errors.sku" class="mt-1 text-xs text-danger">{{ errors.sku[0] }}</p>
                </div>
                <div>
                    <label class="label" for="np-price">Nettó egységár (Ft)</label>
                    <input id="np-price" v-model.number="form.unit_price" type="number" min="0" class="field num" placeholder="49 900" required />
                    <p v-if="errors.unit_price" class="mt-1 text-xs text-danger">{{ errors.unit_price[0] }}</p>
                </div>
            </div>
            <div>
                <label class="label" for="np-reorder">Újrarendelési szint (db)</label>
                <input id="np-reorder" v-model.number="form.reorder_level" type="number" min="0" class="field num" required />
                <p class="mt-1 text-xs text-subtle">Ennél a készletnél vagy alatta napi riasztás megy a raktárnak.</p>
            </div>
            <div>
                <label class="label" for="np-desc">Leírás <span class="font-normal text-subtle">(opcionális)</span></label>
                <textarea id="np-desc" v-model="form.description" rows="3" class="field" maxlength="2000" />
            </div>
            <p v-if="errors._" class="rounded-lg px-3 py-2 text-sm soft-danger" role="alert">{{ errors._[0] }}</p>
        </form>
        <template #footer>
            <div class="flex-1" />
            <Button variant="ghost" @click="emit('close')">Mégse</Button>
            <Button variant="primary" type="submit" form="new-product" :loading="busy">Termék létrehozása</Button>
        </template>
    </Drawer>
</template>
