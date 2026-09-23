<script setup>
import { computed } from 'vue';
import { hue, initials } from '../../lib/format.js';

const props = defineProps({ name: { type: String, default: '' }, size: { type: String, default: 'md' } });
const style = computed(() => {
    const h = hue(props.name);
    return {
        background: `color-mix(in oklab, hsl(${h} 70% 55%) 16%, var(--surface))`,
        color: `color-mix(in oklab, hsl(${h} 70% 42%) 50%, var(--fg))`,
    };
});
const sizes = { sm: 'size-7 text-[11px]', md: 'size-9 text-xs', lg: 'size-12 text-base' };
</script>

<template>
    <span class="grid shrink-0 place-items-center rounded-full font-semibold tracking-wide" :class="sizes[size]" :style="style" aria-hidden="true">{{ initials(name) || '?' }}</span>
</template>
