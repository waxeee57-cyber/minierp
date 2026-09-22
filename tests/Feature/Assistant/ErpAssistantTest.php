<?php

namespace Tests\Feature\Assistant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Modules\Assistant\Ai\ErpAssistant;
use Modules\Assistant\Ai\Tools\CustomerProfile;
use Modules\Assistant\Ai\Tools\FindCustomer;
use Modules\Assistant\Ai\Tools\OpenTasks;
use Modules\Assistant\Ai\Tools\SalesSummary;
use Modules\Assistant\Ai\Tools\StockOutlook;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use RuntimeException;
use Tests\TestCase;

class ErpAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function tool(string $class, array $args = []): array
    {
        return json_decode(app($class)->handle(new Request($args)), true);
    }

    public function test_is_disabled_gracefully_without_an_api_key(): void
    {
        config(['ai.providers.anthropic.key' => null]);

        $this->postJson('/api/assistant/ask', ['question' => 'Mi fogy ki a héten?'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ANTHROPIC_API_KEY'));
    }

    public function test_answers_through_the_laravel_ai_sdk(): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        ErpAssistant::fake(['A Jabra headset 3 nap múlva elfogy, rendelj 12 darabot.']);

        $this->postJson('/api/assistant/ask', ['question' => 'Mi fogy ki a héten?'])
            ->assertOk()
            ->assertJsonPath('data.answer', 'A Jabra headset 3 nap múlva elfogy, rendelj 12 darabot.');

        ErpAssistant::assertPrompted('Mi fogy ki a héten?');
    }

    public function test_provider_failure_returns_a_clean_502(): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        ErpAssistant::fake(fn () => throw new RuntimeException('timeout'));

        $this->postJson('/api/assistant/ask', ['question' => 'Mennyi volt a bevétel?'])->assertStatus(502);
    }

    public function test_exposes_exactly_the_read_only_tools(): void
    {
        $this->assertSame(
            [FindCustomer::class, CustomerProfile::class, SalesSummary::class, StockOutlook::class, OpenTasks::class],
            collect(ErpAssistant::make()->tools())->map(fn ($t) => $t::class)->all(),
        );
    }

    public function test_tools_return_real_data_without_contact_details(): void
    {
        $customer = Customer::factory()->create(['company' => 'Bakony Bau Kft.', 'email' => 'titok@bakony.example', 'phone' => '+36 30 111 2222']);
        $product = app(StockLedger::class)->record(Product::factory()->create(['stock' => 0, 'unit_price' => 50_000, 'name' => 'Dokkoló']), 10, 'purchase');
        $order = app(PlaceOrder::class)->handle($customer->id, [['product_id' => $product->id, 'quantity' => 4]]);
        app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

        $found = $this->tool(FindCustomer::class, ['query' => 'Bakony']);
        $this->assertSame($customer->id, $found['results'][0]['id']);

        $profile = json_encode($this->tool(CustomerProfile::class, ['customer_id' => $customer->id]));
        $this->assertStringContainsString('200000', $profile);
        $this->assertStringNotContainsString('titok@bakony.example', $profile);
        $this->assertStringNotContainsString('111 2222', $profile);

        $sales = $this->tool(SalesSummary::class, ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(200_000, $sales['revenue']);
        $this->assertSame('Bakony Bau Kft.', $sales['top_customers'][0]['customer']);
        $this->assertSame('Dokkoló', $sales['top_products'][0]['name']);

        $stock = $this->tool(StockOutlook::class);
        $this->assertSame(6, $stock['items'][0]['stock']);
    }

    public function test_sales_tool_validates_its_arguments(): void
    {
        $this->expectException(ValidationException::class);

        app(SalesSummary::class)->handle(new Request(['from' => 'tegnap', 'to' => '2026-01-01']));
    }
}
