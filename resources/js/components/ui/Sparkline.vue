<script setup>
import { computed } from 'vue';

const props = defineProps({ values: { type: Array, default: () => [] }, tone: { type: String, default: 'accent' } });
const W = 100;
const H = 28;
const gid = `sp-${Math.random().toString(36).slice(2, 8)}`;

const path = computed(() => {
    const v = props.values.length > 1 ? props.values : [0, 0];
    const max = Math.max(...v, 1);
    const pts = v.map((y, i) => [(i / (v.length - 1)) * W, H - 2 - (y / max) * (H - 4)]);
    const line = pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(2)},${p[1].toFixed(2)}`).join(' ');
    return { line, area: `${line} L${W},${H} L0,${H} Z` };
});
</script>

<template>
    <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="h-7 w-full overflow-visible" :style="{ color: `var(--${tone})` }" aria-hidden="true">
        <defs>
            <linearGradient :id="gid" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity="0.22" /><stop offset="1" stop-color="currentColor" stop-opacity="0" /></linearGradient>
        </defs>
        <path :d="path.area" :fill="`url(#${gid})`" />
        <path :d="path.line" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
    </svg>
</template>
