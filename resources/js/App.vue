<script setup>
import { computed, onMounted, ref } from 'vue';
import Dashboard from './views/Dashboard.vue';
import Orders from './views/Orders.vue';
import Inventory from './views/Inventory.vue';
import Customers from './views/Customers.vue';
import ToastHost from './components/ToastHost.vue';

const views = [
    { key: 'dashboard', label: 'Vezérlőpult', component: Dashboard },
    { key: 'orders', label: 'Rendelések', component: Orders },
    { key: 'inventory', label: 'Készlet', component: Inventory },
    { key: 'customers', label: 'Ügyfelek', component: Customers },
];

const current = ref('dashboard');
const focus = ref(null); // pl. { customerId: 3 } – nézetek közti ugráshoz
const active = computed(() => views.find((v) => v.key === current.value));

function go(key, payload = null) {
    current.value = key;
    focus.value = payload;
    const url = new URL(window.location);
    url.searchParams.set('view', key);
    window.history.replaceState(null, '', url);
}

onMounted(() => {
    const v = new URLSearchParams(window.location.search).get('view');
    if (views.some((x) => x.key === v)) current.value = v;
});
</script>

<template>
    <div class="min-h-screen md:flex">
        <aside class="border-b border-slate-200 bg-white md:sticky md:top-0 md:h-screen md:w-60 md:shrink-0 md:border-r md:border-b-0">
            <div class="flex items-center gap-2.5 px-5 py-4">
                <div class="grid size-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">E</div>
                <div>
                    <div class="text-sm font-semibold">Mini-ERP</div>
                    <div class="text-xs text-slate-500">Irodatechnika Kft. · demó</div>
                </div>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col md:pb-0" aria-label="Fő navigáció">
                <button
                    v-for="v in views"
                    :key="v.key"
                    class="rounded-lg px-3 py-2 text-left text-sm font-medium whitespace-nowrap transition"
                    :class="current === v.key ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'"
                    :aria-current="current === v.key ? 'page' : undefined"
                    @click="go(v.key)"
                >
                    {{ v.label }}
                </button>
            </nav>
            <div class="hidden px-5 pt-6 text-xs leading-relaxed text-slate-400 md:absolute md:bottom-5 md:block">
                Laravel 13 · moduláris · Vue 3<br />
                Szilágyi Roland munkamintája
            </div>
        </aside>

        <ToastHost />
        <main class="min-w-0 flex-1 px-4 py-6 md:px-8">
            <component :is="active.component" :focus="focus" @navigate="go" />
        </main>
    </div>
</template>
