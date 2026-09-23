<script setup>
import { computed } from 'vue';
import { AlarmClock, Check, CircleCheck, Mail, MessageSquare, Phone, ShoppingCart, StickyNote, Users } from 'lucide-vue-next';
import { dateTime, relative } from '../../lib/format.js';

const props = defineProps({ items: { type: Array, default: () => [] } });
const emit = defineEmits(['complete']);

const icons = { call: Phone, email: Mail, meeting: Users, note: StickyNote, task: AlarmClock, order: ShoppingCart };

// A rendelés-események (leadás, fizetés, kiszállítás) egy bejegyzéssé állnak össze, hogy az idővonal olvasható maradjon.
const entries = computed(() => {
    const out = [];
    const byOrder = new Map();
    for (const i of props.items) {
        const number = i.type === 'order' ? i.subject.match(/[A-Z]+-\d{4}-\d{5}/)?.[0] : null;
        if (!number) {
            out.push({ kind: 'single', ...i });
            continue;
        }
        let g = byOrder.get(number);
        if (!g) {
            g = { kind: 'order', id: `o-${number}`, number, occurred_at: i.occurred_at, steps: [], body: null };
            byOrder.set(number, g);
            out.push(g);
        }
        const placed = i.subject.startsWith('Új rendelés');
        if (placed) g.body = i.body;
        g.steps.unshift({ label: placed ? 'Leadva' : i.subject.split(': ').pop(), at: i.occurred_at });
    }
    return out;
});

const stepTone = (label) => ({ Fizetve: 'soft-info', Kiszállítva: 'soft-success', Lemondva: 'soft-danger' })[label] ?? 'soft-neutral';
</script>

<template>
    <ol class="relative space-y-1">
        <li v-for="(e, idx) in entries" :key="e.id" class="relative flex gap-3.5 pb-4">
            <div v-if="idx < entries.length - 1" class="absolute top-9 bottom-0 left-4 w-px bg-line" aria-hidden="true" />
            <template v-if="e.kind === 'order'">
                <div class="relative z-10 grid size-8 shrink-0 place-items-center rounded-full border border-line bg-surface text-subtle"><ShoppingCart class="size-4" /></div>
                <div class="min-w-0 flex-1 pt-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm font-medium">Rendelés <span class="font-mono text-[13px]">{{ e.number }}</span></span>
                        <time class="shrink-0 text-xs text-subtle" :datetime="e.occurred_at" :title="dateTime(e.occurred_at)">{{ relative(e.occurred_at) }}</time>
                    </div>
                    <p v-if="e.body" class="text-[13px] text-muted">{{ e.body }}</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                        <span v-for="(s, i) in e.steps" :key="i" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium" :class="stepTone(s.label)" :title="dateTime(s.at)">{{ s.label }}</span>
                    </div>
                </div>
            </template>
            <template v-else>
                <div
                    class="relative z-10 grid size-8 shrink-0 place-items-center rounded-full border"
                    :class="e.type === 'task' ? (e.completed_at ? 'border-transparent soft-success' : e.is_overdue ? 'border-transparent soft-danger' : 'border-transparent soft-warning') : 'border-line bg-surface text-subtle'"
                >
                    <component :is="e.type === 'task' && e.completed_at ? Check : icons[e.type] ?? MessageSquare" class="size-4" />
                </div>
                <div class="min-w-0 flex-1 pt-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm font-medium" :class="e.completed_at ? 'text-subtle line-through' : ''">{{ e.subject }}</span>
                        <time class="shrink-0 text-xs text-subtle" :datetime="e.occurred_at" :title="dateTime(e.occurred_at)">{{ relative(e.occurred_at) }}</time>
                    </div>
                    <p v-if="e.body" class="text-[13px] text-muted">{{ e.body }}</p>
                    <div v-if="e.type === 'task' && !e.completed_at" class="mt-1.5 flex items-center gap-3 text-xs">
                        <span :class="e.is_overdue ? 'font-medium text-danger' : 'text-subtle'">{{ e.is_overdue ? 'Lejárt' : 'Határidő' }}: {{ dateTime(e.due_at) }}</span>
                        <button class="inline-flex items-center gap-1 font-medium text-accent hover:underline" @click="emit('complete', e)"><CircleCheck class="size-3.5" />Kész</button>
                    </div>
                    <p v-else class="text-xs text-subtle">{{ e.type_label }}</p>
                </div>
            </template>
        </li>
    </ol>
</template>
