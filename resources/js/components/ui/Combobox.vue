<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps({
    options: { type: Array, default: () => [] }, // [{ value, label, sub, meta, disabled }]
    placeholder: { type: String, default: 'Válassz…' },
    label: String,
    invalid: Boolean,
});
const model = defineModel();
const open = ref(false);
const query = ref('');
const active = ref(0);
const input = ref(null);
const list = ref(null);
const uid = `cb-${Math.random().toString(36).slice(2, 8)}`;

const selected = computed(() => props.options.find((o) => o.value === model.value));
const norm = (s) => s.toLocaleLowerCase('hu').normalize('NFD').replace(/\p{Diacritic}/gu, '');
const filtered = computed(() => {
    const q = norm(query.value.trim());
    return q ? props.options.filter((o) => norm(`${o.label} ${o.sub ?? ''}`).includes(q)) : props.options;
});

watch(filtered, () => (active.value = Math.max(0, filtered.value.findIndex((o) => !o.disabled))));

function show() {
    open.value = true;
    query.value = '';
    active.value = Math.max(0, filtered.value.findIndex((o) => o.value === model.value));
    nextTick(scrollActive);
}
function pick(o) {
    if (!o || o.disabled) return;
    model.value = o.value;
    open.value = false;
    query.value = '';
}
function move(d) {
    if (!open.value) return show();
    const n = filtered.value.length;
    for (let k = 1; k <= n; k++) {
        const i = (active.value + d * k + n) % n;
        if (!filtered.value[i].disabled) {
            active.value = i;
            break;
        }
    }
    nextTick(scrollActive);
}
function scrollActive() {
    list.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
}
</script>

<template>
    <div class="relative" @focusout="(e) => !e.currentTarget.contains(e.relatedTarget) && (open = false)">
        <div class="field flex items-center gap-2 pr-2" :class="[open ? 'border-accent shadow-[0_0_0_3px_var(--ring)]' : '', invalid ? 'border-danger' : '']">
            <input
                ref="input"
                :value="open ? query : selected?.label ?? ''"
                class="h-full min-w-0 flex-1 bg-transparent outline-none placeholder:text-subtle"
                :placeholder="selected?.label ?? placeholder"
                role="combobox"
                :aria-label="label"
                :aria-expanded="open"
                :aria-controls="uid"
                autocomplete="off"
                @input="(e) => ((query = e.target.value), (open = true))"
                @focus="show"
                @click="!open && show()"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="open && pick(filtered[active])"
                @keydown.esc.stop="open = false"
            />
            <span v-if="selected?.meta && !open" class="num shrink-0 text-xs text-subtle">{{ selected.meta }}</span>
            <ChevronsUpDown class="size-4 shrink-0 text-subtle" />
        </div>
        <ul v-if="open" :id="uid" ref="list" role="listbox" class="absolute inset-x-0 top-[calc(100%+6px)] z-30 max-h-64 animate-pop overflow-y-auto rounded-xl border border-line bg-elevated p-1 shadow-pop">
            <li
                v-for="(o, i) in filtered"
                :key="o.value"
                role="option"
                :aria-selected="o.value === model"
                :aria-disabled="o.disabled"
                :data-active="i === active"
                tabindex="-1"
                class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-sm"
                :class="[o.disabled ? 'cursor-not-allowed opacity-45' : '', i === active && !o.disabled ? 'bg-surface-2' : '']"
                @mousedown.prevent="pick(o)"
                @mousemove="active = i"
            >
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ o.label }}</div>
                    <div v-if="o.sub" class="truncate text-xs text-subtle">{{ o.sub }}</div>
                </div>
                <span v-if="o.meta" class="num shrink-0 text-xs text-muted">{{ o.meta }}</span>
                <Check v-if="o.value === model" class="size-4 shrink-0 text-accent" />
            </li>
            <li v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-subtle">Nincs találat.</li>
        </ul>
    </div>
</template>
