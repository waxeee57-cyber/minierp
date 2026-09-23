<script setup>
import { computed } from 'vue';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'secondary' }, // primary | secondary | ghost | danger
    size: { type: String, default: 'md' }, // sm | md
    loading: Boolean,
    disabled: Boolean,
    to: [String, Object],
    href: String,
    type: { type: String, default: 'button' },
});

const tag = computed(() => (props.to ? 'RouterLink' : props.href ? 'a' : 'button'));
const variants = {
    primary: 'bg-accent text-accent-fg hover:bg-accent-hover shadow-[inset_0_1px_0_rgb(255_255_255/0.15),0_1px_2px_rgb(0_0_0/0.12)]',
    secondary: 'border border-line-strong bg-surface text-fg hover:bg-surface-2 shadow-[0_1px_2px_rgb(0_0_0/0.04)]',
    ghost: 'text-muted hover:bg-surface-2 hover:text-fg',
    danger: 'border border-line-strong bg-surface text-danger hover:bg-[color-mix(in_oklab,var(--danger)_8%,var(--surface))]',
};
const sizes = { sm: 'h-8 px-2.5 text-[13px] gap-1.5 rounded-lg', md: 'h-9 px-3.5 text-sm gap-2 rounded-[10px]', icon: 'size-9 rounded-[10px]' };
</script>

<template>
    <component
        :is="tag"
        v-bind="tag === 'RouterLink' ? { to } : tag === 'a' ? { href } : { type, disabled: disabled || loading }"
        class="inline-flex shrink-0 items-center justify-center font-medium whitespace-nowrap transition-[background-color,color,box-shadow,transform] duration-150 select-none active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0"
        :class="[variants[variant], sizes[size]]"
    >
        <LoaderCircle v-if="loading" class="animate-spin" />
        <slot />
    </component>
</template>
