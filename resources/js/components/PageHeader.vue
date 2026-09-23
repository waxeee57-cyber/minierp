<script setup>
defineProps({ title: String, description: String, eyebrow: String, reserve: Boolean });
</script>

<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4 lg:mb-8">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-1 text-[13px] font-medium text-subtle first-letter:uppercase">{{ eyebrow }}</p>
            <h1 class="text-2xl font-semibold tracking-tight text-balance lg:text-[28px]">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-2xl text-sm text-muted" :class="reserve ? 'min-h-[2lh] sm:min-h-[1lh]' : ''">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
    </header>
</template>
