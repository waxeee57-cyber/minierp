<script setup>
defineProps({ tone: { type: String, default: 'neutral' }, dot: Boolean });
</script>

<template>
    <span class="inline-flex h-[22px] items-center gap-1.5 rounded-full px-2 text-xs font-medium whitespace-nowrap" :class="`soft-${tone}`">
        <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        <slot />
    </span>
</template>
