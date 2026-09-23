<script setup>
import { reactive, ref } from 'vue';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import { api } from '../../lib/api.js';
import { toast } from '../../lib/toast.js';

const props = defineProps({ customer: Object });
const emit = defineEmits(['close', 'saved']);
const fields = ['company', 'name', 'email', 'phone', 'tax_number', 'postal_code', 'city', 'address', 'notes'];
const form = reactive(Object.fromEntries(fields.map((f) => [f, props.customer?.[f] ?? ''])));
const errors = ref({});
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, typeof v === 'string' && !v.trim() ? null : v]));
    try {
        const res = props.customer
            ? await api(`/crm/customers/${props.customer.id}`, { method: 'PATCH', body })
            : await api('/crm/customers', { method: 'POST', body });
        toast(props.customer ? 'Ügyféladatok mentve' : `Ügyfél létrehozva: ${res.data.company ?? res.data.name}`);
        emit('saved', res.data);
    } catch (e) {
        errors.value = Object.keys(e.errors).length ? e.errors : { _: [e.message] };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Drawer :title="customer ? 'Ügyfél szerkesztése' : 'Új ügyfél'" :subtitle="customer ? customer.company ?? customer.name : 'Az adószám kell a NAV-kompatibilis (belföldi áfás) számlához.'" @close="emit('close')">
        <form id="customer-form" class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="c-company">Cégnév</label>
                <input id="c-company" v-model="form.company" class="field" placeholder="pl. Napfény Iroda Kft." autofocus maxlength="160" />
                <p v-if="errors.company" class="mt-1 text-xs text-danger">{{ errors.company[0] }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="c-name">Kapcsolattartó</label>
                    <input id="c-name" v-model="form.name" class="field" required maxlength="120" />
                    <p v-if="errors.name" class="mt-1 text-xs text-danger">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="label" for="c-tax">Adószám</label>
                    <input id="c-tax" v-model="form.tax_number" class="field num" placeholder="12345678-2-41" inputmode="numeric" />
                    <p v-if="errors.tax_number" class="mt-1 text-xs text-danger">{{ errors.tax_number[0] }}</p>
                </div>
                <div>
                    <label class="label" for="c-email">E-mail</label>
                    <input id="c-email" v-model="form.email" type="email" class="field" required maxlength="160" />
                    <p v-if="errors.email" class="mt-1 text-xs text-danger">{{ errors.email[0] }}</p>
                </div>
                <div>
                    <label class="label" for="c-phone">Telefon</label>
                    <input id="c-phone" v-model="form.phone" type="tel" class="field" placeholder="+36 30 123 4567" maxlength="40" />
                </div>
            </div>
            <div class="grid grid-cols-[6.5rem_1fr] gap-4">
                <div><label class="label" for="c-zip">Irsz.</label><input id="c-zip" v-model="form.postal_code" class="field num" inputmode="numeric" maxlength="10" /></div>
                <div><label class="label" for="c-city">Város</label><input id="c-city" v-model="form.city" class="field" maxlength="80" /></div>
            </div>
            <div><label class="label" for="c-address">Cím</label><input id="c-address" v-model="form.address" class="field" placeholder="utca, házszám" maxlength="160" /></div>
            <div><label class="label" for="c-notes">Belső megjegyzés</label><textarea id="c-notes" v-model="form.notes" rows="3" class="field" maxlength="5000" /></div>
            <p v-if="errors._" class="rounded-lg px-3 py-2 text-sm soft-danger" role="alert">{{ errors._[0] }}</p>
        </form>
        <template #footer>
            <div class="flex-1" />
            <Button variant="ghost" @click="emit('close')">Mégse</Button>
            <Button variant="primary" type="submit" form="customer-form" :loading="busy">{{ customer ? 'Mentés' : 'Ügyfél létrehozása' }}</Button>
        </template>
    </Drawer>
</template>
