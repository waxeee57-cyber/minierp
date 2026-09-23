const huf = new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 0 });
const dec = new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 1 });
const dateFmt = new Intl.DateTimeFormat('hu-HU', { month: 'short', day: 'numeric' });
const fullDate = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' });
const longDate = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' });
const timeFmt = new Intl.DateTimeFormat('hu-HU', { hour: '2-digit', minute: '2-digit' });

export const money = (v) => `${huf.format(v ?? 0)} Ft`;
export const number = (v) => huf.format(v ?? 0);
export const decimal = (v) => dec.format(v ?? 0);

/** 5 987 430 → „5,99 M Ft”; kis összegnél teljes alak. */
export function compactMoney(v) {
    const n = v ?? 0;
    if (Math.abs(n) >= 1_000_000) return `${new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 2 }).format(n / 1_000_000)} M Ft`;
    if (Math.abs(n) >= 100_000) return `${huf.format(Math.round(n / 1000))} E Ft`;
    if (Math.abs(n) >= 10_000) return `${dec.format(n / 1000)} E Ft`;
    return money(n);
}

export const date = (v) => (v ? dateFmt.format(new Date(v)) : '–');
export const isoDate = (v) => (v ? fullDate.format(new Date(v)).replace(/\s/g, '') : '–');
export const today = () => longDate.format(new Date());

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

/** „ma 14:20”, „tegnap 09:12”, „3 napja”, „szept. 8.” */
export function relative(v) {
    if (!v) return '–';
    const d = new Date(v);
    const days = Math.round((startOfDay(new Date()) - startOfDay(d)) / 86_400_000);
    if (days === 0) return `ma ${timeFmt.format(d)}`;
    if (days === 1) return `tegnap ${timeFmt.format(d)}`;
    if (days === -1) return `holnap ${timeFmt.format(d)}`;
    if (days > 1 && days < 7) return `${days} napja`;
    if (days < -1 && days > -7) return `${-days} nap múlva`;
    return dateFmt.format(d);
}

export const dateTime = (v) => (v ? `${dateFmt.format(new Date(v))} ${timeFmt.format(new Date(v))}` : '–');

/** Százalékos változás; null, ha nincs értelmes bázis. */
export function delta(current, previous) {
    if (!previous) return current ? null : 0;
    return ((current - previous) / previous) * 100;
}

export function initials(name = '') {
    return name
        .replace(/\b(Kft|Zrt|Bt|Nyrt)\.?/gi, '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

/** Determinisztikus árnyalat névből: ugyanaz az ügyfél mindig ugyanolyan színű. */
export function hue(name = '') {
    let h = 0;
    for (const c of name) h = (h * 31 + c.charCodeAt(0)) % 360;
    return h;
}
