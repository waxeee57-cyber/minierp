<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

defineProps({ title: String, wide: Boolean });
const emit = defineEmits(['close']);
const onKey = (e) => e.key === 'Escape' && emit('close');
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" @click.self="emit('close')">
        <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-md'" role="dialog" aria-modal="true" :aria-label="title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold">{{ title }}</h2>
                <button class="rounded p-1 text-slate-400 hover:text-slate-700" aria-label="Bezárás" @click="emit('close')">✕</button>
            </div>
            <div class="p-5"><slot /></div>
        </div>
    </div>
</template>
