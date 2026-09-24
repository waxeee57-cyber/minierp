<?php

/*
|--------------------------------------------------------------------------
| Vercel belépési pont (élő demó)
|--------------------------------------------------------------------------
|
| A Vercel függvények fájlrendszere csak olvasható, egyedül a /tmp írható.
| Ezért itt minden írható útvonalat (storage, bootstrap-cache, SQLite) a
| /tmp alá irányítunk, és hidegindításkor – illetve óránként – friss
| demóadatbázist építünk. Titkos kulcs nincs a repóban: ha a projektben
| nincs APP_KEY, példányonként véletlen kulcs készül (az API állapotmentes).
|
*/

$tmp = '/tmp/mini-erp';

foreach ([
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap-cache',
] as $dir) {
    if (! is_dir("{$tmp}/{$dir}")) {
        @mkdir("{$tmp}/{$dir}", 0777, true);
    }
}

$setEnv = static function (string $key, string $value, bool $override = true): void {
    if (! $override && getenv($key) !== false && getenv($key) !== '') {
        return;
    }

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

if (getenv('APP_KEY') === false || getenv('APP_KEY') === '') {
    $keyFile = "{$tmp}/app.key";
    if (! is_file($keyFile)) {
        file_put_contents($keyFile, 'base64:'.base64_encode(random_bytes(32)), LOCK_EX);
    }
    $setEnv('APP_KEY', trim((string) file_get_contents($keyFile)));
}

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$db = "{$tmp}/database.sqlite";

foreach ([
    'APP_NAME' => 'Mini-ERP',
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => "https://{$host}",
    'APP_LOCALE' => 'hu',
    'APP_FALLBACK_LOCALE' => 'en',
    'LARAVEL_STORAGE_PATH' => "{$tmp}/storage",
    'APP_PACKAGES_CACHE' => "{$tmp}/bootstrap-cache/packages.php",
    'APP_SERVICES_CACHE' => "{$tmp}/bootstrap-cache/services.php",
    'APP_CONFIG_CACHE' => "{$tmp}/bootstrap-cache/config.php",
    'APP_ROUTES_CACHE' => "{$tmp}/bootstrap-cache/routes.php",
    'APP_EVENTS_CACHE' => "{$tmp}/bootstrap-cache/events.php",
    'VIEW_COMPILED_PATH' => "{$tmp}/storage/framework/views",
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $db,
    'DB_SYNCHRONOUS' => 'off',
    'DB_JOURNAL_MODE' => 'memory',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'stderr',
    'LOG_LEVEL' => 'warning',
    'MAIL_MAILER' => 'log',
    'SHOPIFY_WEBHOOK_SECRET' => 'demo-shopify-secret',
    'WOOCOMMERCE_WEBHOOK_SECRET' => 'demo-woocommerce-secret',
] as $key => $value) {
    // A projektben beállított környezeti változó mindig nyer.
    $setEnv($key, $value, override: false);
}

/*
| Demóadatbázis: hidegindításkor, és ha egy óránál régebbi, újraépül.
| Külön folyamatban migrál + seedel ideiglenes fájlba, majd atomikusan
| a helyére nevezi – párhuzamos kérések sosem látnak félkész adatbázist.
*/
$seededAt = "{$db}.seeded";
$stale = ! is_file($db) || ! is_file($seededAt) || (time() - (int) file_get_contents($seededAt)) > 3600;

if ($stale) {
    $lock = fopen("{$tmp}/seed.lock", 'c');
    flock($lock, LOCK_EX);

    clearstatcache();
    $stale = ! is_file($db) || ! is_file($seededAt) || (time() - (int) file_get_contents($seededAt)) > 3600;

    if ($stale) {
        $building = "{$db}.building";
        @unlink($building);
        touch($building);

        $env = array_merge(getenv(), ['DB_DATABASE' => $building]);
        // A Vercel a php.ini-t -c kapcsolóval adja át; a gyerekfolyamat is ezt kapja.
        $ini = php_ini_loaded_file();
        $command = array_merge(
            [PHP_BINARY],
            $ini ? ['-c', $ini] : [],
            [dirname(__DIR__).'/artisan', 'migrate', '--seed', '--force', '--no-interaction'],
        );
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
            $env,
        );
        $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        $exit = proc_close($process);

        if ($exit === 0) {
            rename($building, $db);
            file_put_contents($seededAt, (string) time());
        } else {
            error_log("[mini-erp] demo seed failed ({$exit}): {$out}");
        }
    }

    flock($lock, LOCK_UN);
    fclose($lock);
}

// A belépő az api/ mappában van, de a Laravel a gyökérről szolgál ki: ha a
// SCRIPT_NAME /api/index.php maradna, a Symfony „/api” base URL-t vezetne le,
// és minden /api/* kérés elveszítené az előtagját.
// A Vercel csak HTTPS-en szolgál ki, a függvény viszont belül HTTP-n kapja a
// kérést; enélkül a Vite-assetek http:// URL-t kapnának (mixed content).
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_SCHEME'] = 'https';

$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
unset($_SERVER['ORIG_SCRIPT_NAME']);

require __DIR__.'/../public/index.php';
