<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Github, LayoutDashboard, Moon, Package, Receipt, Search, ShoppingCart, Store, Sun, Users } from 'lucide-vue-next';
import CommandPalette from './components/CommandPalette.vue';
import Kbd from './components/ui/Kbd.vue';
import Toaster from './components/ui/Toaster.vue';
import { badges, refreshBadges } from './lib/badges.js';
import { openPalette, paletteOpen } from './lib/palette.js';
import { isDark, toggleTheme } from './lib/theme.js';

const route = useRoute();
const router = useRouter();
const isMac = /Mac|iPhone|iPad/.test(navigator.platform);

const nav = [
    { name: 'dashboard', to: '/', label: 'Vezérlőpult', short: 'Főoldal', icon: LayoutDashboard },
    { name: 'orders', to: '/orders', label: 'Rendelések', short: 'Rendelés', icon: ShoppingCart, badge: () => badges.pending, tone: 'warning' },
    { name: 'inventory', to: '/inventory', label: 'Készlet', short: 'Készlet', icon: Package, badge: () => badges.lowStock, tone: 'danger' },
    { name: 'customers', to: '/customers', label: 'Ügyfelek', short: 'Ügyfél', icon: Users, badge: () => badges.overdueTasks, tone: 'danger' },
    { name: 'invoices', to: '/invoices', label: 'Számlák', short: 'Számla', icon: Receipt },
    { name: 'channels', to: '/channels', label: 'Webshop', short: 'Webshop', icon: Store },
];

// Billentyűparancsok: ⌘K / Ctrl+K / „/” paletta, „C” új rendelés, „G” + betű ugrás.
const jumps = { d: '/', o: '/orders', k: '/inventory', u: '/customers', s: '/invoices', w: '/channels' };
let gPressed = 0;
function onKey(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        paletteOpen.value = !paletteOpen.value;
        return;
    }
    const t = e.target;
    if (e.metaKey || e.ctrlKey || e.altKey || paletteOpen.value || t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || document.querySelector('[role="dialog"]')) return;
    const k = e.key.toLowerCase();
    if (k === '/') return (e.preventDefault(), openPalette());
    if (Date.now() - gPressed < 900 && jumps[k]) return ((gPressed = 0), router.push(jumps[k]));
    if (k === 'g') return (gPressed = Date.now());
    if (k === 'c') return router.push('/orders/new');
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    refreshBadges();
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="min-h-dvh lg:flex">
        <!-- Oldalsáv (asztali) -->
        <aside class="sticky top-0 hidden h-dvh w-[252px] shrink-0 flex-col border-r border-line bg-surface/60 px-3 py-4 backdrop-blur lg:flex">
            <div class="flex items-center gap-2.5 px-2 pb-5">
                <div class="grid size-8 place-items-center rounded-[9px] bg-accent text-accent-fg shadow-[inset_0_1px_0_rgb(255_255_255/0.2)]">
                    <svg viewBox="0 0 32 32" class="size-5" aria-hidden="true"><path d="M8 10h16M8 16h11M8 22h16" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" /></svg>
                </div>
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-semibold tracking-tight">Mini-ERP</div>
                    <div class="truncate text-xs text-subtle">Irodatechnika Demo Kft.</div>
                </div>
            </div>

            <button class="mb-4 flex h-9 items-center gap-2 rounded-[10px] border border-line bg-surface px-2.5 text-sm text-subtle transition hover:border-line-strong hover:text-muted" @click="openPalette">
                <Search class="size-4" />
                <span class="flex-1 text-left">Keresés…</span>
                <Kbd>{{ isMac ? '⌘' : 'Ctrl' }}</Kbd><Kbd>K</Kbd>
            </button>

            <nav class="flex flex-col gap-0.5" aria-label="Fő navigáció">
                <RouterLink
                    v-for="n in nav"
                    :key="n.name"
                    :to="n.to"
                    class="group flex h-9 items-center gap-2.5 rounded-[9px] px-2.5 text-sm font-medium transition-colors"
                    :class="route.name === n.name ? 'bg-surface-2 text-fg shadow-[inset_0_0_0_1px_var(--line)]' : 'text-muted hover:bg-surface-2/70 hover:text-fg'"
                    :aria-current="route.name === n.name ? 'page' : undefined"
                >
                    <component :is="n.icon" class="size-4" :class="route.name === n.name ? 'text-accent' : 'text-subtle group-hover:text-muted'" />
                    <span class="flex-1">{{ n.label }}</span>
                    <span v-if="n.badge?.()" class="num min-w-5 rounded-full px-1.5 text-center text-[11px] leading-5 font-semibold" :class="`soft-${n.tone}`">{{ n.badge() }}</span>
                </RouterLink>
            </nav>

            <div class="mt-auto space-y-3 px-1">
                <div class="rounded-xl border border-line bg-surface p-3 text-xs leading-relaxed text-subtle">
                    <div class="mb-1 font-medium text-muted">Billentyűparancsok</div>
                    <div class="flex items-center justify-between"><span>Új rendelés</span><Kbd>C</Kbd></div>
                    <div class="mt-1 flex items-center justify-between"><span>Ugrás</span><span class="flex gap-1"><Kbd>G</Kbd><Kbd>O</Kbd></span></div>
                </div>
                <div class="flex items-center gap-1">
                    <button class="grid size-8 place-items-center rounded-lg text-subtle transition hover:bg-surface-2 hover:text-fg" :aria-label="isDark ? 'Világos téma' : 'Sötét téma'" @click="toggleTheme">
                        <component :is="isDark ? Sun : Moon" class="size-4" />
                    </button>
                    <a href="https://github.com/waxeee57-cyber/minierp" target="_blank" rel="noopener" class="grid size-8 place-items-center rounded-lg text-subtle transition hover:bg-surface-2 hover:text-fg" aria-label="Forráskód a GitHubon"><Github class="size-4" /></a>
                    <span class="ml-auto text-right text-[11px] leading-tight text-subtle">Laravel 13 · Vue 3<br />Szilágyi Roland</span>
                </div>
            </div>
        </aside>

        <!-- Felső sáv (mobil) -->
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-bg/80 px-4 backdrop-blur-lg lg:hidden">
            <div class="grid size-7 place-items-center rounded-lg bg-accent text-accent-fg">
                <svg viewBox="0 0 32 32" class="size-4" aria-hidden="true"><path d="M8 10h16M8 16h11M8 22h16" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
            </div>
            <span class="flex-1 text-sm font-semibold">Mini-ERP</span>
            <button class="grid size-9 place-items-center rounded-lg text-muted" aria-label="Keresés" @click="openPalette"><Search class="size-[18px]" /></button>
            <button class="grid size-9 place-items-center rounded-lg text-muted" :aria-label="isDark ? 'Világos téma' : 'Sötét téma'" @click="toggleTheme"><component :is="isDark ? Sun : Moon" class="size-[18px]" /></button>
        </header>

        <main class="min-w-0 flex-1 px-4 pt-5 pb-28 sm:px-6 lg:px-10 lg:pt-8 lg:pb-12">
            <div class="mx-auto max-w-[1280px]">
                <RouterView v-slot="{ Component }">
                    <component :is="Component" />
                </RouterView>
            </div>
        </main>

        <!-- Alsó fülsáv (mobil) -->
        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-6 border-t border-line bg-bg/85 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg lg:hidden" aria-label="Fő navigáció">
            <RouterLink v-for="n in nav" :key="n.name" :to="n.to" class="relative flex h-16 flex-col items-center justify-center gap-1 text-[10.5px] font-medium" :class="route.name === n.name ? 'text-accent' : 'text-subtle'">
                <component :is="n.icon" class="size-5" />
                {{ n.short }}
                <span v-if="n.badge?.()" class="num absolute top-2 left-[calc(50%+6px)] min-w-4 rounded-full bg-danger px-1 text-center text-[10px] leading-4 font-semibold text-white">{{ n.badge() }}</span>
            </RouterLink>
        </nav>

        <CommandPalette />
        <Toaster />
    </div>
</template>
