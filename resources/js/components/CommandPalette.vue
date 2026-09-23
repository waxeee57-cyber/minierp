<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Box, CornerDownLeft, LayoutDashboard, Moon, Package, Plus, Receipt, Search, ShoppingCart, Sparkles, Sun, UserPlus, Users,
} from 'lucide-vue-next';
import { api } from '../lib/api.js';
import { paletteOpen } from '../lib/palette.js';
import { isDark, toggleTheme } from '../lib/theme.js';
import Kbd from './ui/Kbd.vue';

const router = useRouter();
const query = ref('');
const results = ref([]);
const loading = ref(false);
const active = ref(0);
const input = ref(null);
const list = ref(null);
let timer;
let ctrl;

const typeMeta = {
    customer: { group: 'Ügyfelek', icon: Users, to: (r) => `/customers/${r.id}` },
    product: { group: 'Termékek', icon: Box, to: (r) => `/inventory/${r.id}` },
    order: { group: 'Rendelések', icon: ShoppingCart, to: (r) => `/orders/${r.id}` },
    invoice: { group: 'Számlák', icon: Receipt, to: (r) => `/invoices/${r.id}` },
};

const actions = computed(() => [
    { group: 'Ugrás', icon: LayoutDashboard, title: 'Vezérlőpult', hint: 'G D', run: () => router.push('/') },
    { group: 'Ugrás', icon: ShoppingCart, title: 'Rendelések', hint: 'G O', run: () => router.push('/orders') },
    { group: 'Ugrás', icon: Package, title: 'Készlet', hint: 'G K', run: () => router.push('/inventory') },
    { group: 'Ugrás', icon: Users, title: 'Ügyfelek', hint: 'G U', run: () => router.push('/customers') },
    { group: 'Ugrás', icon: Receipt, title: 'Számlák', hint: 'G S', run: () => router.push('/invoices') },
    { group: 'Művelet', icon: Plus, title: 'Új rendelés', hint: 'C', run: () => router.push('/orders/new') },
    { group: 'Művelet', icon: UserPlus, title: 'Új ügyfél', run: () => router.push('/customers/new') },
    { group: 'Művelet', icon: Box, title: 'Új termék', run: () => router.push('/inventory/new') },
    { group: 'Művelet', icon: isDark.value ? Sun : Moon, title: isDark.value ? 'Világos téma' : 'Sötét téma', run: toggleTheme },
]);

const norm = (s) => s.toLocaleLowerCase('hu').normalize('NFD').replace(/\p{Diacritic}/gu, '');

const items = computed(() => {
    const q = norm(query.value.trim());
    const found = results.value.map((r) => ({ group: typeMeta[r.type].group, icon: typeMeta[r.type].icon, title: r.title, sub: r.subtitle, run: () => router.push(typeMeta[r.type].to(r)) }));
    const acts = q ? actions.value.filter((a) => norm(a.title).includes(q)) : actions.value;
    const ask = query.value.trim().length >= 3 ? [{ group: 'AI', icon: Sparkles, title: `Kérdezd az ERP-t: „${query.value.trim()}”`, run: () => router.push({ path: '/', query: { ask: query.value.trim() } }) }] : [];
    return [...found, ...acts, ...ask];
});

const groups = computed(() => {
    const out = [];
    items.value.forEach((it, index) => {
        let g = out.find((x) => x.name === it.group);
        if (!g) out.push((g = { name: it.group, items: [] }));
        g.items.push({ ...it, index });
    });
    return out;
});

watch(query, (q) => {
    active.value = 0;
    clearTimeout(timer);
    ctrl?.abort();
    if (q.trim().length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    timer = setTimeout(async () => {
        ctrl = new AbortController();
        try {
            results.value = (await api(`/search?q=${encodeURIComponent(q.trim())}`, { signal: ctrl.signal })).data;
        } catch (e) {
            if (e.name !== 'AbortError') results.value = [];
        } finally {
            loading.value = false;
        }
    }, 140);
});

watch(paletteOpen, async (open) => {
    if (!open) return;
    query.value = '';
    results.value = [];
    await nextTick();
    input.value?.focus();
});

function run(it) {
    if (!it) return;
    paletteOpen.value = false;
    it.run();
}

function move(d) {
    const n = items.value.length;
    if (!n) return;
    active.value = (active.value + d + n) % n;
    nextTick(() => list.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}
</script>

<template>
    <Teleport to="body">
        <div v-if="paletteOpen" class="fixed inset-0 z-50 flex items-start justify-center px-3 pt-[12vh]" @keydown.esc="paletteOpen = false">
            <div class="absolute inset-0 animate-in bg-black/30 backdrop-blur-sm dark:bg-black/60" @click="paletteOpen = false" />
            <div class="relative w-full max-w-xl animate-pop overflow-hidden rounded-2xl border border-line bg-elevated shadow-pop" role="dialog" aria-modal="true" aria-label="Parancspaletta">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Search class="size-4 shrink-0 text-subtle" :class="loading ? 'animate-pulse' : ''" />
                    <input
                        ref="input"
                        v-model="query"
                        class="h-14 flex-1 bg-transparent text-[15px] outline-none placeholder:text-subtle"
                        placeholder="Keress ügyfelet, terméket, rendelést, számlát – vagy kérdezz…"
                        aria-label="Keresés"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="run(items[active])"
                        @keydown.esc.prevent="paletteOpen = false"
                    />
                    <Kbd>Esc</Kbd>
                </div>
                <div ref="list" class="max-h-[min(60vh,420px)] overflow-y-auto p-2">
                    <div v-for="g in groups" :key="g.name" class="mb-1">
                        <div class="px-2.5 pt-2 pb-1.5 text-[11px] font-semibold tracking-wider text-subtle uppercase">{{ g.name }}</div>
                        <button
                            v-for="it in g.items"
                            :key="it.index"
                            :data-active="it.index === active"
                            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm"
                            :class="it.index === active ? 'bg-surface-2' : ''"
                            @mousemove="active = it.index"
                            @click="run(it)"
                        >
                            <component :is="it.icon" class="size-4 shrink-0" :class="it.group === 'AI' ? 'text-accent' : 'text-subtle'" />
                            <span class="min-w-0 flex-1 truncate"><span class="font-medium">{{ it.title }}</span><span v-if="it.sub" class="ml-2 text-subtle">{{ it.sub }}</span></span>
                            <span v-if="it.hint" class="flex gap-1"><Kbd v-for="k in it.hint.split(' ')" :key="k">{{ k }}</Kbd></span>
                            <CornerDownLeft v-else-if="it.index === active" class="size-3.5 text-subtle" />
                        </button>
                    </div>
                    <p v-if="!items.length && !loading" class="px-3 py-10 text-center text-sm text-subtle">Nincs találat erre: „{{ query }}”.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
