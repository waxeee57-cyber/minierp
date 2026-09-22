<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A modulhatárok automatikus őre. Ha valaki a CRM-ből közvetlenül az Orders
 * modult importálja, vagy az Orders a készlettáblákat a szerződés helyett
 * közvetlenül írja, ez a teszt elbukik.
 */
class ModuleBoundariesTest extends TestCase
{
    public static function forbidden(): array
    {
        return [
            'CRM nem függhet az Orders modultól' => ['Crm', ['Modules\\Orders\\']],
            'CRM nem függhet az Inventory modultól' => ['Crm', ['Modules\\Inventory\\']],
            'Inventory nem függhet más modultól' => ['Inventory', ['Modules\\Orders\\', 'Modules\\Crm\\']],
            'Orders csak szerződésen át nyúl a készlethez' => ['Orders', ['Modules\\Inventory\\Models\\StockMovement', 'Modules\\Inventory\\Services\\']],
            'Orders csak szerződésen át ír az idővonalra' => ['Orders', ['Modules\\Crm\\Models\\Interaction', 'Modules\\Crm\\Services\\']],
            'Az AI-asszisztens csak szerződéseken át olvas' => ['Assistant', ['Modules\\Crm\\Models', 'Modules\\Orders\\Models', 'Modules\\Inventory\\Models', 'Modules\\Crm\\Services', 'Modules\\Orders\\Services', 'Modules\\Inventory\\Services', 'Modules\\Orders\\Actions']],
            'Az AI-asszisztens nem írhat (nincs készlet- és idővonal-írás)' => ['Assistant', ['Modules\\Inventory\\Contracts\\StockLedger', 'Modules\\Crm\\Contracts\\Timeline']],
            'A számlázás nem nyúl a készlethez' => ['Invoicing', ['Modules\\Inventory\\']],
            'Az Orders nem tud a számlázásról' => ['Orders', ['Modules\\Invoicing\\']],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_module_does_not_reach_across_boundaries(string $module, array $namespaces): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../../modules/{$module}"));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Database/Factories/')) {
                continue;
            }

            $code = file_get_contents($file->getPathname());

            foreach ($namespaces as $ns) {
                $this->assertStringNotContainsString("use {$ns}", $code, "{$file->getFilename()} átlépi a modulhatárt: {$ns}");
            }
        }
    }
}
