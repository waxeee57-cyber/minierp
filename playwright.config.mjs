import { defineConfig, devices } from '@playwright/test';

// Böngészős füstteszt: friss, bemutató adatokkal feltöltött adatbázison kattintja végig a fő folyamatokat.
export default defineConfig({
    testDir: 'tests/Browser',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: { baseURL: 'http://127.0.0.1:8123', trace: 'retain-on-failure', locale: 'hu-HU' },
    projects: [
        { name: 'asztali', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'mobil', use: { ...devices['Pixel 7'] }, grep: /@mobil/ },
    ],
    webServer: {
        command: 'php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123',
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
