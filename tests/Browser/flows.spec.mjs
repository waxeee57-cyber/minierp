import { expect, test } from '@playwright/test';

// Minden teszt figyeli a konzolt: futásidejű JS-hiba = bukás.
let pageErrors = [];
test.beforeEach(async ({ page }) => {
    pageErrors = [];
    page.on('pageerror', (e) => pageErrors.push(e.message));
});
test.afterEach(async () => expect(pageErrors, 'futásidejű JS-hiba').toEqual([]));

test('vezérlőpult: KPI-k, grafikon, asszisztens kulcs nélkül is válaszol @mobil', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByText('Realizált bevétel')).toBeVisible();
    await expect(page.locator('svg[role="img"]').first()).toBeVisible();
    await page.getByRole('button', { name: /Mi fogy ki 14 napon/ }).click();
    await expect(page.getByText(/Szabályalapú mód|AI · Laravel AI SDK/)).toBeVisible();
    await expect(page.getByText('forrás:')).toBeVisible();
});

test('⌘K paletta: modulokon átívelő keresés és ugrás', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Control+k');
    await page.getByPlaceholder(/Keress ügyfelet/).fill('bakony');
    await page.getByRole('button', { name: /Bakony Bau Kft\./ }).first().waitFor();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/customers\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Bakony Bau Kft.' })).toBeVisible();
});

test('rendelés: készlethiány jelzése, rögzítés, fizetés → NAV-számla → XML', async ({ page }) => {
    await page.goto('/orders/new');
    await page.getByRole('combobox', { name: 'Ügyfél' }).click();
    await page.keyboard.type('napfény');
    await page.keyboard.press('Enter');
    await page.getByRole('combobox', { name: '1. termék' }).click();
    await page.keyboard.type('thinkpad');
    await page.keyboard.press('Enter');
    await page.getByLabel('1. mennyiség').fill('999');
    await expect(page.getByText(/Csak \d+ db van készleten/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Rendelés rögzítése' })).toBeDisabled();
    await page.getByLabel('1. mennyiség').fill('1');
    await page.getByRole('button', { name: 'Rendelés rögzítése' }).click();
    await expect(page).toHaveURL(/\/orders\/\d+$/);

    await page.getByRole('button', { name: /Fizetve · számla/ }).click();
    await expect(page.getByText(/Számla kiállítva: SZ-/)).toBeVisible();
    await page.getByRole('link', { name: /SZ-2026-/ }).click();
    await expect(page.getByText('Fizetendő végösszeg')).toBeVisible();
    await page.getByRole('tab', { name: /NAV XML/ }).click();
    await expect(page.locator('.xml')).toContainText('InvoiceData');
    await expect(page.locator('.xml')).toContainText('DOMESTIC');
});

test('lemondás megerősítéssel: a készlet visszakerül', async ({ page }) => {
    await page.goto('/orders?status=pending');
    await page.locator('tbody tr').first().click();
    await page.getByRole('button', { name: 'Lemondás' }).click();
    await page.getByRole('button', { name: /Biztosan/ }).click();
    await expect(page.getByText('A lefoglalt készlet visszakerült a raktárba.')).toBeVisible();
});

test('készlet: bevételezés az előrejelzés javaslatából', async ({ page }) => {
    await page.goto('/inventory?filter=reorder');
    await page.locator('tbody tr').first().click();
    await page.getByRole('button', { name: /Bevételezés előkészítése/ }).click();
    await page.getByRole('button', { name: 'Rögzítés' }).click();
    await expect(page.getByText(/Bevételezve: \d+ db/)).toBeVisible();
    await expect(page.getByText('Előrejelzés alapján').first()).toBeVisible();
});

test('ügyfél: magyar validáció, létrehozás, teendő felvétele és lezárása', async ({ page }) => {
    await page.goto('/customers/new');
    await page.getByLabel('Kapcsolattartó').fill('Teszt Elek');
    await page.getByLabel('E-mail').fill('elek@teszt.example');
    await page.getByLabel('Adószám').fill('123');
    await page.getByRole('button', { name: 'Ügyfél létrehozása' }).click();
    await expect(page.getByText('Az adószám formátuma: 12345678-2-41.')).toBeVisible();
    await page.getByLabel('Adószám').fill('12345678-2-41');
    await page.getByLabel('Cégnév').fill('Teszt Kft.');
    await page.getByRole('button', { name: 'Ügyfél létrehozása' }).click();
    await expect(page.getByRole('heading', { name: 'Teszt Kft.' })).toBeVisible();

    await page.getByRole('tab', { name: 'Teendő' }).click();
    await page.getByPlaceholder(/Mit kell tenni/).fill('Bemutatkozó hívás');
    await page.getByLabel('Határidő').fill('2030-01-15T10:00');
    await page.getByRole('button', { name: 'Mentés', exact: true }).click();
    await expect(page.getByText('Teendő létrehozva')).toBeVisible();
    await page.getByRole('button', { name: 'Kész' }).click();
    await expect(page.getByText('Teendő lezárva')).toBeVisible();
});

test('billentyűparancsok és mélylinkek', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('g');
    await page.keyboard.press('s');
    await expect(page).toHaveURL(/\/invoices$/);
    await page.keyboard.press('c');
    await expect(page).toHaveURL(/\/orders\/new$/);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/orders$/);
    await page.goto('/orders/999999');
    await expect(page).toHaveURL(/\/orders$/);
});

test('mobil: alsó fülsáv navigáció és rendelés részletei @mobil', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'csak mobilon');
    await page.goto('/');
    await page.getByRole('navigation', { name: 'Fő navigáció' }).getByRole('link', { name: /Rendelés/ }).click();
    await expect(page).toHaveURL(/\/orders$/);
    await page.locator('ul li a').first().click();
    await expect(page.getByText('Tételek')).toBeVisible();
});

test('webshop: marketing-riport, beérkezett rendelések, integráció', async ({ page }) => {
    await page.goto('/channels');
    await expect(page.getByText('Bevétel marketingforrás szerint')).toBeVisible();
    await expect(page.getByText('Google Ads', { exact: true })).toBeVisible();
    await expect(page.getByText(/Ismeretlen vagy inaktív cikkszám/)).toBeVisible();
    await expect(page.getByText('Google Merchant Center termékfeed')).toBeVisible();
    await page.locator('a[href^="/orders/"]').first().click();
    await expect(page).toHaveURL(/\/orders\/\d+$/);
    await expect(page.getByRole('link', { name: /Shopify|WooCommerce/ })).toBeVisible();
});
