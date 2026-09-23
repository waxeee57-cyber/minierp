<script setup>
defineProps({ icon: Object, title: String, text: String });
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div v-if="icon" class="mb-3 grid size-11 place-items-center rounded-xl border border-line bg-surface-2 text-subtle"><component :is="icon" class="size-5" /></div>
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="text" class="mt-1 max-w-sm text-sm text-subtle">{{ text }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
