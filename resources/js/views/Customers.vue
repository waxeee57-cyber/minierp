<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Plus, Search, UserRound } from 'lucide-vue-next';
import CustomerDetail from '../components/customers/CustomerDetail.vue';
import CustomerFormDrawer from '../components/customers/CustomerFormDrawer.vue';
import PageHeader from '../components/PageHeader.vue';
import Avatar from '../components/ui/Avatar.vue';
import Button from '../components/ui/Button.vue';
import Empty from '../components/ui/Empty.vue';
import { api } from '../lib/api.js';
import { toastError } from '../lib/toast.js';

const route = useRoute();
const router = useRouter();
const customers = ref(null);
const search = ref('');
const desktop = window.matchMedia('(min-width: 1024px)');

async function load() {
    try {
        customers.value = (await api('/crm/customers?per_page=100')).data;
    } catch (e) {
        toastError(e);
    }
}
load();

const id = computed(() => route.params.id);
const selectedId = computed(() => (id.value && id.value !== 'new' ? id.value : null));

// Asztali nézetben mindig legyen kiválasztott ügyfél (az első), mobilon a lista a kezdőképernyő.
watch([customers, id], () => {
    if (!id.value && desktop.matches && customers.value?.length) router.replace(`/customers/${customers.value[0].id}`);
});

const norm = (s) => (s ?? '').toLocaleLowerCase('hu').normalize('NFD').replace(/\p{Diacritic}/gu, '');
const rows = computed(() => {
    const q = norm(search.value.trim());
    return (customers.value ?? []).filter((c) => !q || norm(`${c.company} ${c.name} ${c.city} ${c.email}`).includes(q));
});

function onCreated(c) {
    load();
    router.replace(`/customers/${c.id}`);
}
</script>

<template>
    <PageHeader title="Ügyfelek" description="Idővonal, teendők és helyzetkép. Minden rendelés és állapotváltás automatikusan megjelenik az ügyfél idővonalán.">
        <Button variant="primary" to="/customers/new"><Plus />Új ügyfél</Button>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-[19rem_minmax(0,1fr)]">
        <aside class="card flex flex-col self-start overflow-hidden lg:sticky lg:top-8 lg:max-h-[calc(100dvh-4rem)]" :class="selectedId ? 'hidden lg:flex' : ''">
            <div class="border-b border-line p-3">
                <label class="relative block">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                    <input v-model="search" type="search" class="field pl-9" placeholder="Cég, név, város…" aria-label="Ügyfél keresése" />
                </label>
            </div>
            <ul v-if="customers" class="min-h-0 flex-1 overflow-y-auto p-1.5">
                <li v-for="c in rows" :key="c.id">
                    <RouterLink
                        :to="`/customers/${c.id}`"
                        class="flex items-center gap-3 rounded-[10px] px-2.5 py-2.5 transition"
                        :class="String(c.id) === selectedId ? 'bg-surface-2 shadow-[inset_0_0_0_1px_var(--line)]' : 'hover:bg-surface-2/60'"
                    >
                        <Avatar :name="c.company ?? c.name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ c.company ?? c.name }}</div>
                            <div class="truncate text-xs text-subtle">{{ c.name }}<template v-if="c.city"> · {{ c.city }}</template></div>
                        </div>
                        <span v-if="c.open_tasks_count" class="num rounded-full px-1.5 text-[11px] leading-5 font-semibold soft-warning" :title="`${c.open_tasks_count} nyitott teendő`">{{ c.open_tasks_count }}</span>
                    </RouterLink>
                </li>
                <li v-if="!rows.length" class="px-3 py-8 text-center text-sm text-subtle">Nincs találat.</li>
            </ul>
            <div v-else class="space-y-2 p-3"><div v-for="i in 7" :key="i" class="skeleton h-11" /></div>
        </aside>

        <div class="min-w-0" :class="selectedId ? '' : 'hidden lg:block'">
            <CustomerDetail v-if="selectedId" :id="selectedId" @changed="load" @back="router.push('/customers')" />
            <div v-else class="card"><Empty :icon="UserRound" title="Válassz ügyfelet" text="Vagy hozz létre újat a jobb felső gombbal." /></div>
        </div>
    </div>

    <CustomerFormDrawer v-if="id === 'new'" @close="router.push('/customers')" @saved="onCreated" />
</template>
