<script setup>
import { ref } from 'vue';
import { api } from '../api.js';

const question = ref('');
const answer = ref(null);
const tools = ref([]);
const error = ref('');
const loading = ref(false);
const disabled = ref(false);

const examples = ['Mi fogy ki a következő 7 napban, és mennyit rendeljek?', 'Ki volt a legjobb ügyfelünk az elmúlt 30 napban?', 'Milyen lejárt teendőink vannak?'];

const toolLabels = { FindCustomer: 'ügyfélkeresés', CustomerProfile: 'ügyfél-adatlap', SalesSummary: 'értékesítési riport', StockOutlook: 'készlet-előrejelzés', OpenTasks: 'teendők' };

async function ask(q = question.value) {
    if (!q || q.length < 3) return;
    question.value = q;
    loading.value = true;
    error.value = '';
    answer.value = null;
    try {
        const res = await api('/assistant/ask', { method: 'POST', body: { question: q } });
        answer.value = res.data.answer;
        tools.value = res.data.tools_used;
    } catch (e) {
        if (e.status === 503) disabled.value = true;
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold">Kérdezd az ERP-t</h2>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">Laravel AI SDK · csak olvas</span>
        </div>
        <div class="space-y-3 p-5">
            <form class="flex gap-2" @submit.prevent="ask()">
                <input id="ask-erp" v-model="question" class="input" placeholder="pl. Mi fogy ki jövő héten?" aria-label="Kérdés az ERP-nek" maxlength="500" />
                <button class="btn-primary shrink-0" :disabled="loading">{{ loading ? 'Gondolkodik…' : 'Kérdezem' }}</button>
            </form>
            <div class="flex flex-wrap gap-1.5">
                <button v-for="ex in examples" :key="ex" type="button" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-200" @click="ask(ex)">{{ ex }}</button>
            </div>
            <div v-if="answer" class="rounded-lg bg-brand-50/60 p-4 text-sm leading-relaxed whitespace-pre-line ring-1 ring-brand-100">
                {{ answer }}
                <div v-if="tools.length" class="mt-2 flex flex-wrap gap-1 text-[11px] text-slate-500">
                    Forrás: <span v-for="t in tools" :key="t" class="rounded bg-white px-1.5 ring-1 ring-slate-200">{{ toolLabels[t] ?? t }}</span>
                </div>
            </div>
            <p v-if="error" class="rounded-lg px-3 py-2 text-sm" :class="disabled ? 'bg-slate-50 text-slate-600' : 'bg-red-50 text-red-700'">
                {{ error }}
                <span v-if="disabled" class="block pt-1 text-xs text-slate-500">Az eszközök (ügyfél, értékesítés, készlet-előrejelzés, teendők) kulcs nélkül is tesztelve vannak: <code>tests/Feature/Assistant</code>.</span>
            </p>
        </div>
    </div>
</template>
