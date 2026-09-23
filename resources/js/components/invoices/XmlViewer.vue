<script setup>
import { computed } from 'vue';

const props = defineProps({ xml: String });

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// Minimális, biztonságos kiemelő: előbb escape, utána csak saját span-ek kerülnek a szövegbe.
function highlight(line) {
    return esc(line)
        .replace(/(&lt;\/?)([\w:.-]+)([^&]*?)(\/?&gt;)/g, (_, open, name, attrs, close) => {
            const a = attrs.replace(/([\w:.-]+)(=)("[^"]*")/g, '<span class="x-attr">$1</span>$2<span class="x-str">$3</span>');
            const [ns, local] = name.includes(':') ? name.split(':') : [null, name];
            return `<span class="x-punct">${open}</span>${ns ? `<span class="x-ns">${ns}:</span>` : ''}<span class="x-tag">${local}</span>${a}<span class="x-punct">${close}</span>`;
        })
        .replace(/(&gt;)([^<]+)(&lt;)/g, '$1<span class="x-text">$2</span>$3');
}

const lines = computed(() => (props.xml ?? '').trimEnd().split('\n').map(highlight));
</script>

<template>
    <div class="xml overflow-auto rounded-xl border border-line bg-surface-2/60 py-3 font-mono text-[12px] leading-[1.65]">
        <div v-for="(l, i) in lines" :key="i" class="flex min-w-max pr-4">
            <span class="w-11 shrink-0 pr-3 text-right text-subtle/70 select-none">{{ i + 1 }}</span>
            <!-- eslint-disable-next-line vue/no-v-html – a highlight() escape-el, a sor csak saját span-eket tartalmaz -->
            <span class="whitespace-pre" v-html="l" />
        </div>
    </div>
</template>

<style scoped>
.xml :deep(.x-tag) { color: var(--accent); }
.xml :deep(.x-ns) { color: var(--fg-subtle); }
.xml :deep(.x-attr) { color: var(--warning); }
.xml :deep(.x-str) { color: var(--success); }
.xml :deep(.x-punct) { color: var(--fg-subtle); }
.xml :deep(.x-text) { color: var(--fg); font-weight: 500; }
</style>
