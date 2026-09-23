<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowRight, LoaderCircle, Sparkles } from 'lucide-vue-next';
import { api } from '../lib/api.js';

const route = useRoute();
const question = ref('');
const result = ref(null);
const error = ref('');
const loading = ref(false);
const input = ref(null);

const examples = ['Mi fogy ki 14 napon belül, és mennyit rendeljek?', 'Ki volt a legjobb ügyfelünk az elmúlt 30 napban?', 'Milyen lejárt teendőink vannak?', 'Hogy áll a Bakony Bau?'];
const toolLabels = { FindCustomer: 'ügyfélkeresés', CustomerProfile: 'ügyfél-adatlap', SalesSummary: 'értékesítési riport', StockOutlook: 'készlet-előrejelzés', OpenTasks: 'teendőlista' };

async function ask(q = question.value) {
    q = q.trim();
    if (q.length < 3 || loading.value) return;
    question.value = q;
    loading.value = true;
    error.value = '';
    result.value = null;
    try {
        result.value = (await api('/assistant/ask', { method: 'POST', body: { question: q } })).data;
    } catch (e) {
        error.value = e.first;
    } finally {
        loading.value = false;
    }
}

const fromRoute = () => route.query.ask && ask(String(route.query.ask));
onMounted(fromRoute);
watch(() => route.query.ask, fromRoute);
defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <section class="card relative overflow-hidden">
        <div class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--accent)_22%,transparent),transparent)]" aria-hidden="true" />
        <div class="relative p-4 sm:p-5">
            <form class="flex items-center gap-2 rounded-xl border border-line-strong bg-surface py-1.5 pr-1.5 pl-3.5 transition focus-within:border-accent focus-within:shadow-[0_0_0_3px_var(--ring)]" @submit.prevent="ask()">
                <Sparkles class="size-[18px] shrink-0 text-accent" />
                <input ref="input" v-model="question" class="h-9 min-w-0 flex-1 bg-transparent text-[15px] outline-none placeholder:text-subtle" placeholder="Kérdezd az ERP-t – pl. mi fogy ki jövő héten?" aria-label="Kérdés az ERP-nek" maxlength="500" />
                <button class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-accent text-accent-fg transition hover:bg-accent-hover disabled:opacity-50" :disabled="loading || question.trim().length < 3" aria-label="Kérdés elküldése">
                    <LoaderCircle v-if="loading" class="size-4 animate-spin" /><ArrowRight v-else class="size-4" />
                </button>
            </form>

            <div v-if="!result && !loading && !error" class="mt-3 flex gap-1.5 overflow-x-auto pb-0.5 sm:flex-wrap">
                <button v-for="ex in examples" :key="ex" type="button" class="shrink-0 rounded-full border border-line bg-surface px-3 py-1 text-[13px] text-muted transition hover:border-line-strong hover:text-fg" @click="ask(ex)">{{ ex }}</button>
            </div>

            <div v-if="loading" class="mt-4 space-y-2" aria-live="polite">
                <div class="skeleton h-3 w-3/4" /><div class="skeleton h-3 w-1/2" /><div class="skeleton h-3 w-2/3" />
            </div>

            <div v-if="result" class="mt-4 animate-pop" aria-live="polite">
                <p class="text-[15px] leading-relaxed whitespace-pre-line">{{ result.answer }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-subtle">
                    <span class="rounded-md px-1.5 py-0.5 font-medium" :class="result.mode === 'ai' ? 'soft-accent' : 'soft-neutral'">
                        {{ result.mode === 'ai' ? 'AI · Laravel AI SDK' : result.degraded ? 'Szabályalapú tartalék (AI nem elérhető)' : 'Szabályalapú mód · API-kulcs nélkül' }}
                    </span>
                    <template v-if="result.tools_used.length">
                        <span>forrás:</span>
                        <span v-for="t in result.tools_used" :key="t" class="rounded-md border border-line px-1.5 py-0.5">{{ toolLabels[t] ?? t }}</span>
                    </template>
                    <button class="ml-auto font-medium text-accent hover:underline" @click="(result = null), (question = ''), input?.focus()">Új kérdés</button>
                </div>
            </div>

            <p v-if="error" class="mt-3 rounded-lg px-3 py-2 text-sm soft-danger">{{ error }}</p>
        </div>
    </section>
</template>
