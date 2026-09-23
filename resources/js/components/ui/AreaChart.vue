<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { compactMoney, date as fmtDate } from '../../lib/format.js';

/**
 * Függőség nélküli SVG-grafikon: több sorozat, rácsvonalak, egér- és
 * érintésvezérelt célkereszt tooltippel. A szélességet a konténer adja.
 */
const props = defineProps({
    points: { type: Array, default: () => [] }, // [{ date, [key]: number }]
    series: { type: Array, default: () => [] }, // [{ key, label, tone, dashed, fill }]
    height: { type: Number, default: 240 },
    format: { type: Function, default: compactMoney },
    formatX: { type: Function, default: fmtDate },
    step: Boolean,
    timeScale: Boolean, // x a valós időt követi (egyenetlen mozgásoknál), nem az indexet
});

const box = ref(null);
const width = ref(600);
const hover = ref(null);
const pad = { t: 12, r: 8, b: 26, l: 56 };
let ro;

onMounted(() => {
    ro = new ResizeObserver(([e]) => (width.value = Math.max(240, e.contentRect.width)));
    ro.observe(box.value);
});
onBeforeUnmount(() => ro?.disconnect());

const innerW = computed(() => width.value - pad.l - pad.r);
const innerH = computed(() => props.height - pad.t - pad.b);

// „Szép” skála: 4 egyenlő lépés 1–1,5–2–2,5–3–4–5–6–8–10 × 10ⁿ lépésközzel.
const max = computed(() => {
    const m = Math.max(1, ...props.points.flatMap((p) => props.series.map((s) => p[s.key] ?? 0))) * 1.06;
    const raw = m / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find((f) => f * mag >= raw) * mag;
    return step * 4;
});

const times = computed(() => props.points.map((p) => new Date(p.date).getTime()));
const x = (i) => {
    const n = props.points.length;
    if (n <= 1) return pad.l + innerW.value / 2;
    if (!props.timeScale) return pad.l + (i / (n - 1)) * innerW.value;
    const [t0, t1] = [times.value[0], times.value[n - 1]];
    return pad.l + ((times.value[i] - t0) / Math.max(1, t1 - t0)) * innerW.value;
};
const y = (v) => pad.t + innerH.value - (v / max.value) * innerH.value;

/** Monoton köbös görbe (Fritsch–Carlson): sima, de sosem lő túl – nem mutat 0 alatti bevételt. */
function monotone(pts) {
    const n = pts.length;
    if (n < 3) return pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join('');
    const dx = [], dy = [], m = [];
    for (let i = 0; i < n - 1; i++) {
        dx.push(pts[i + 1][0] - pts[i][0]);
        dy.push(pts[i + 1][1] - pts[i][1]);
        m.push(dy[i] / dx[i]);
    }
    const t = [m[0]];
    for (let i = 1; i < n - 1; i++) t.push(m[i - 1] * m[i] <= 0 ? 0 : (3 * (dx[i - 1] + dx[i])) / ((2 * dx[i] + dx[i - 1]) / m[i - 1] + (dx[i] + 2 * dx[i - 1]) / m[i]));
    t.push(m[n - 2]);
    let d = `M${pts[0][0].toFixed(1)},${pts[0][1].toFixed(1)}`;
    for (let i = 0; i < n - 1; i++) {
        const h = dx[i] / 3;
        d += `C${(pts[i][0] + h).toFixed(1)},${(pts[i][1] + h * t[i]).toFixed(1)} ${(pts[i + 1][0] - h).toFixed(1)},${(pts[i + 1][1] - h * t[i + 1]).toFixed(1)} ${pts[i + 1][0].toFixed(1)},${pts[i + 1][1].toFixed(1)}`;
    }
    return d;
}

function line(key) {
    const pts = props.points.map((p, i) => [x(i), y(p[key] ?? 0)]);
    if (!props.step) return monotone(pts);
    return pts.map(([px, py], i) => (i ? `H${px.toFixed(1)}V${py.toFixed(1)}` : `M${px.toFixed(1)},${py.toFixed(1)}`)).join('');
}

const paths = computed(() =>
    props.series.map((s) => {
        const l = line(s.key);
        return { ...s, line: l, area: `${l}L${x(props.points.length - 1)},${pad.t + innerH.value}L${x(0)},${pad.t + innerH.value}Z` };
    }),
);

const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => ({ v: max.value * f, y: y(max.value * f) })));
const xLabels = computed(() => {
    const out = [];
    let last = -Infinity;
    props.points.forEach((p, i) => {
        const px = x(i);
        if (px - last >= 84 && px <= width.value - pad.r - 30) {
            out.push({ i, label: props.formatX(p.date) });
            last = px;
        }
    });
    return out;
});

function onMove(e) {
    const r = box.value.getBoundingClientRect();
    const px = e.clientX - r.left;
    let best = null;
    let dist = Infinity;
    props.points.forEach((_, i) => {
        const d = Math.abs(x(i) - px);
        if (d < dist) [best, dist] = [i, d];
    });
    hover.value = best;
}

const tip = computed(() => {
    if (hover.value === null) return null;
    const p = props.points[hover.value];
    const left = x(hover.value);
    return { p, left, flip: left > width.value - 180 };
});
const gid = `ac-${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <div ref="box" class="relative w-full touch-pan-y select-none" :style="{ height: `${height}px` }" @pointermove="onMove" @pointerdown="onMove" @pointerleave="hover = null">
        <svg :width="width" :height="height" class="block overflow-visible" role="img" :aria-label="series.map((s) => s.label).join(', ')">
            <defs>
                <linearGradient v-for="s in paths" :id="`${gid}-${s.key}`" :key="s.key" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0" :stop-color="`var(--${s.tone})`" stop-opacity="0.24" />
                    <stop offset="1" :stop-color="`var(--${s.tone})`" stop-opacity="0" />
                </linearGradient>
            </defs>
            <g>
                <line v-for="t in ticks" :key="t.v" :x1="pad.l" :x2="width - pad.r" :y1="t.y" :y2="t.y" stroke="var(--line)" :stroke-dasharray="t.v ? '3 4' : ''" />
                <text v-for="t in ticks" :key="`l${t.v}`" :x="pad.l - 10" :y="t.y + 4" text-anchor="end" class="fill-subtle text-[11px] num">{{ t.v ? format(t.v) : '0' }}</text>
                <text v-for="l in xLabels" :key="l.i" :x="x(l.i)" :y="height - 6" text-anchor="middle" class="fill-subtle text-[11px]">{{ l.label }}</text>
            </g>
            <g v-for="s in paths" :key="s.key">
                <path v-if="s.fill !== false" :d="s.area" :fill="`url(#${gid}-${s.key})`" />
                <path :d="s.line" fill="none" :stroke="`var(--${s.tone})`" stroke-width="2" :stroke-dasharray="s.dashed ? '5 5' : ''" stroke-linejoin="round" stroke-linecap="round" />
            </g>
            <g v-if="tip">
                <line :x1="tip.left" :x2="tip.left" :y1="pad.t" :y2="pad.t + innerH" stroke="var(--line-strong)" />
                <circle v-for="s in series" :key="s.key" :cx="tip.left" :cy="y(tip.p[s.key] ?? 0)" r="4" :fill="`var(--${s.tone})`" stroke="var(--surface)" stroke-width="2" />
            </g>
        </svg>
        <div
            v-if="tip"
            class="pointer-events-none absolute top-2 z-10 min-w-40 rounded-xl border border-line bg-elevated px-3 py-2.5 text-xs shadow-pop"
            :style="tip.flip ? { right: `${width - tip.left + 12}px` } : { left: `${tip.left + 12}px` }"
        >
            <div class="mb-1.5 font-medium text-muted">{{ formatX(tip.p.date) }}</div>
            <div v-for="s in series" :key="s.key" class="flex items-center justify-between gap-4 py-0.5">
                <span class="flex items-center gap-1.5 text-subtle"><span class="size-2 rounded-full" :style="{ background: `var(--${s.tone})` }" />{{ s.label }}</span>
                <span class="num font-semibold text-fg">{{ format(tip.p[s.key] ?? 0) }}</span>
            </div>
        </div>
    </div>
</template>
