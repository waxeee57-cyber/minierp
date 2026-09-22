<script setup>
import { toasts } from './Toast.js';
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col gap-2" aria-live="polite">
        <div v-for="t in toasts" :key="t.id" class="rounded-lg px-4 py-2.5 text-sm font-medium shadow-lg" :class="t.tone === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white'">
            {{ t.message }}
        </div>
    </div>
</template>
