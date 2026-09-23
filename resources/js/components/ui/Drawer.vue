<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { X } from 'lucide-vue-next';

defineProps({ title: String, subtitle: String, wide: Boolean });
const emit = defineEmits(['close']);
const panel = ref(null);
let previous = null;

// Egymásra nyíló panelek: az Esc mindig csak a legfelsőt zárja.
const stack = (window.__drawerStack ??= []);
const id = Symbol('drawer');

function onKey(e) {
    if (e.key === 'Escape' && stack.at(-1) === id) {
        e.stopPropagation();
        emit('close');
    }
    if (e.key === 'Tab' && stack.at(-1) === id && panel.value) {
        const f = [...panel.value.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')];
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) (e.preventDefault(), f.at(-1).focus());
        else if (!e.shiftKey && document.activeElement === f.at(-1)) (e.preventDefault(), f[0].focus());
    }
}

onMounted(async () => {
    previous = document.activeElement;
    stack.push(id);
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKey);
    await nextTick();
    (panel.value?.querySelector('[autofocus]') ?? panel.value)?.focus({ preventScroll: true });
});

onBeforeUnmount(() => {
    stack.splice(stack.indexOf(id), 1);
    if (!stack.length) document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
    previous?.focus?.({ preventScroll: true });
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 animate-in bg-black/25 backdrop-blur-[2px] dark:bg-black/55" @click="emit('close')" />
            <section
                ref="panel"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="relative flex h-full w-full animate-drawer flex-col border-l border-line bg-elevated shadow-pop outline-none sm:m-2 sm:h-[calc(100%-1rem)] sm:rounded-2xl sm:border"
                :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-xl'"
            >
                <header class="flex items-start gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <slot name="header">
                            <h2 class="truncate text-base font-semibold tracking-tight">{{ title }}</h2>
                            <p v-if="subtitle" class="mt-0.5 truncate text-sm text-subtle">{{ subtitle }}</p>
                        </slot>
                    </div>
                    <slot name="actions" />
                    <button class="-mr-1.5 grid size-8 place-items-center rounded-lg text-subtle transition hover:bg-surface-2 hover:text-fg" aria-label="Bezárás (Esc)" @click="emit('close')"><X class="size-4" /></button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6"><slot /></div>
                <footer v-if="$slots.footer" class="flex items-center gap-2 border-t border-line px-5 py-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))] sm:px-6"><slot name="footer" /></footer>
            </section>
        </div>
    </Teleport>
</template>
