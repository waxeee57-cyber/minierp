<script setup>
import { CircleCheck, TriangleAlert, X } from 'lucide-vue-next';
import { dismiss, toasts } from '../../lib/toast.js';
</script>

<template>
    <div class="pointer-events-none fixed inset-x-3 bottom-20 z-[60] flex flex-col items-center gap-2 sm:bottom-6 lg:bottom-6" aria-live="polite">
        <TransitionGroup enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0 translate-x-4" enter-active-class="transition duration-200" leave-active-class="transition duration-200">
            <div v-for="t in toasts" :key="t.id" class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-elevated px-4 py-3 shadow-pop">
                <component :is="t.tone === 'danger' ? TriangleAlert : CircleCheck" class="mt-0.5 size-4 shrink-0" :class="t.tone === 'danger' ? 'text-danger' : 'text-success'" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ t.message }}</p>
                    <p v-if="t.description" class="mt-0.5 text-[13px] text-subtle">{{ t.description }}</p>
                </div>
                <button class="text-subtle hover:text-fg" aria-label="Értesítés bezárása" @click="dismiss(t.id)"><X class="size-3.5" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
