<script setup>
import Delta from './Delta.vue';
import Sparkline from './Sparkline.vue';

/**
 * KPI-kártya rögzített szerkezettel (1 soros címke, érték, 2 soros tipp, opcionális
 * sparkline), így betöltéskor és utána is ugyanakkora – nincs elrendezés-ugrás.
 */
defineProps({ label: String, value: String, hint: String, delta: Number, spark: Array, tone: { type: String, default: 'accent' }, to: [String, Object], icon: Object, alert: Boolean, loading: Boolean, withSpark: Boolean });
</script>

<template>
    <component :is="to && !loading ? 'RouterLink' : 'div'" :to="to" class="card group relative flex flex-col overflow-hidden p-4 transition" :class="to ? 'hover:border-line-strong hover:shadow-[0_2px_12px_-4px_rgb(0_0_0/0.08)]' : ''">
        <div class="flex h-5 items-center gap-2">
            <component :is="icon" v-if="icon" class="size-4 shrink-0 text-subtle" />
            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-muted">{{ label }}</span>
            <span v-if="alert && !loading" class="relative flex size-2 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-danger opacity-60" /><span class="relative inline-flex size-2 rounded-full bg-danger" /></span>
        </div>
        <div class="num mt-2.5 h-[clamp(20px,4.6vw,28px)] text-[clamp(18px,4.6vw,26px)] leading-none font-semibold tracking-tight whitespace-nowrap">
            <div v-if="loading" class="skeleton h-full w-3/4" />
            <template v-else>{{ value }}</template>
        </div>
        <div class="mt-2 line-clamp-2 min-h-[2lh] text-xs leading-[18px] text-subtle">
            <Delta v-if="delta !== undefined && !loading" :value="delta" class="mr-1 align-[1px]" />{{ loading ? '' : hint }}
        </div>
        <div v-if="withSpark" class="mt-2 h-7">
            <Sparkline v-if="spark && !loading" :values="spark" :tone="tone" />
        </div>
    </component>
</template>
