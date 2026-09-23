<script setup>
import { computed } from 'vue';
import { ArrowDownRight, ArrowUpRight } from 'lucide-vue-next';
import { decimal } from '../../lib/format.js';

const props = defineProps({ value: Number, invert: Boolean });
const good = computed(() => (props.invert ? props.value < 0 : props.value >= 0));
</script>

<template>
    <span v-if="value === null || value === undefined" class="text-xs text-subtle">nincs bázis</span>
    <span v-else class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium num" :class="good ? 'soft-success' : 'soft-danger'">
        <component :is="value >= 0 ? ArrowUpRight : ArrowDownRight" class="size-3.5" />{{ decimal(Math.abs(value)) }}%
    </span>
</template>
