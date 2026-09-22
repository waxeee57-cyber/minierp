<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * A Laravel Blueprint-et modulra szabva futtatja.
 *
 * A Blueprint alapból az app/ és a database/ mappába generál. Ez a parancs
 * a modul saját draft.yaml-jéből dolgozik, a modelleket a modul névterébe
 * teszi, majd a migrációkat és a factory-ket átmozgatja a modul
 * Database mappájába, így minden modul önálló "mini Laravel-app" marad.
 */
class ModuleBlueprintCommand extends Command
{
    protected $signature = 'erp:blueprint {module : A modul neve, pl. Crm}';

    protected $description = 'Modell, migráció és factory generálása egy modul draft.yaml fájljából';

    public function handle(Filesystem $files): int
    {
        $module = Str::studly($this->argument('module'));
        $modulePath = base_path("modules/{$module}");
        $draft = "{$modulePath}/draft.yaml";

        if (! $files->exists($draft)) {
            $this->error("Nincs draft: modules/{$module}/draft.yaml");

            return self::FAILURE;
        }

        config([
            'blueprint.namespace' => "Modules\\{$module}",
            'blueprint.app_path' => "modules/{$module}",
        ]);

        $migrationsBefore = $files->glob(database_path('migrations/*.php'));
        $factoriesBefore = $files->glob(database_path('factories/*.php'));

        $this->call('blueprint:build', [
            'draft' => $draft,
            '--only' => 'models,migrations,factories',
        ]);

        $files->ensureDirectoryExists("{$modulePath}/Database/migrations");
        $files->ensureDirectoryExists("{$modulePath}/Database/Factories");

        foreach (array_diff($files->glob(database_path('migrations/*.php')), $migrationsBefore) as $migration) {
            $files->move($migration, "{$modulePath}/Database/migrations/".basename($migration));
        }

        foreach (array_diff($files->glob(database_path('factories/*.php')), $factoriesBefore) as $factory) {
            $class = basename($factory, '.php');
            $model = Str::beforeLast($class, 'Factory');
            $code = str_replace(
                ['namespace Database\\Factories;', "class {$class} extends Factory\n{"],
                ["namespace Modules\\{$module}\\Database\\Factories;", "class {$class} extends Factory\n{\n    protected \$model = \\Modules\\{$module}\\Models\\{$model}::class;\n"],
                $files->get($factory),
            );
            $files->put("{$modulePath}/Database/Factories/{$class}.php", $code);
            $files->delete($factory);
        }

        // A Blueprint 'timestamp' castot generál (unix int); API-hoz a datetime a helyes.
        foreach ($files->glob("{$modulePath}/Models/*.php") as $model) {
            $files->put($model, str_replace("=> 'timestamp'", "=> 'datetime'", $files->get($model)));
        }

        $this->info("A(z) {$module} modul váza elkészült.");

        return self::SUCCESS;
    }
}
