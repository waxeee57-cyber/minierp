<?php

namespace Tests\Feature\App;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleBlueprintCommandTest extends TestCase
{
    private string $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = base_path('modules/Scratch');
        File::ensureDirectoryExists($this->module);
        File::put("{$this->module}/draft.yaml", "models:\n  Supplier:\n    name: string:120\n    contacted_at: timestamp nullable\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->module);
        parent::tearDown();
    }

    public function test_generates_a_self_contained_module_skeleton(): void
    {
        $migrationsBefore = File::glob(database_path('migrations/*.php'));

        $this->artisan('erp:blueprint', ['module' => 'scratch'])->assertSuccessful();

        $model = File::get("{$this->module}/Models/Supplier.php");
        $this->assertStringContainsString('namespace Modules\Scratch\Models;', $model);
        $this->assertStringContainsString("'contacted_at' => 'datetime'", $model);

        $factory = File::get("{$this->module}/Database/Factories/SupplierFactory.php");
        $this->assertStringContainsString('namespace Modules\Scratch\Database\Factories;', $factory);
        $this->assertStringContainsString('protected $model = \Modules\Scratch\Models\Supplier::class;', $factory);

        $this->assertCount(1, File::glob("{$this->module}/Database/migrations/*_create_suppliers_table.php"));
        $this->assertSame($migrationsBefore, File::glob(database_path('migrations/*.php')), 'A database/ mappában nem marad szemét.');
        $this->assertFileDoesNotExist(database_path('factories/SupplierFactory.php'));
    }

    public function test_fails_cleanly_without_a_draft(): void
    {
        $this->artisan('erp:blueprint', ['module' => 'NincsIlyen'])
            ->expectsOutput('Nincs draft: modules/NincsIlyen/draft.yaml')
            ->assertFailed();
    }
}
