<script setup>
defineProps({ options: Array, label: String, wrap: Boolean });
const model = defineModel();
</script>

<template>
    <div class="inline-flex max-w-full rounded-[10px] border border-line bg-surface-2 p-0.5" :class="wrap ? 'flex-wrap' : 'overflow-x-auto'" role="tablist" :aria-label="label">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            role="tab"
            :aria-selected="model === o.value"
            class="flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium whitespace-nowrap transition-colors"
            :class="model === o.value ? 'bg-surface text-fg shadow-[0_1px_2px_rgb(0_0_0/0.08),0_0_0_1px_var(--line)]' : 'text-muted hover:text-fg'"
            @click="model = o.value"
        >
            <component :is="o.icon" v-if="o.icon" class="size-3.5" />
            {{ o.label }}
            <span v-if="o.count !== undefined" class="num text-xs text-subtle">{{ o.count }}</span>
        </button>
    </div>
</template>
