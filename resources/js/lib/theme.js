import { ref } from 'vue';

export const isDark = ref(document.documentElement.classList.contains('dark'));

export function toggleTheme() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    try {
        localStorage.setItem('erp-theme', isDark.value ? 'dark' : 'light');
    } catch {
        // privát mód: a téma a munkamenet végéig él
    }
}
