<?php

namespace Tests\Feature\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Crm\Models\Customer;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class CustomerSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_based_summary_without_api_key(): void
    {
        config(['erp.ai.key' => null]);
        $customer = Customer::factory()->create(['name' => 'Teszt Elek']);
        Order::factory()->for($customer)->placedDaysAgo(10)->create(['status' => 'paid', 'total' => 125_000]);

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'rules')
            ->assertJsonPath('data.summary', fn ($s) => str_contains($s, '1 rendelés') && str_contains($s, "125\u{00A0}000 Ft") && str_contains($s, 'érdemes felhívni'));
    }

    public function test_claude_summary_uses_only_structured_context(): void
    {
        config(['erp.ai.key' => 'test-key', 'erp.ai.model' => 'test-model']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Hűséges ügyfél, hívd fel a Q4 igényekről.']]])]);

        $customer = Customer::factory()->create();

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'claude')
            ->assertJsonPath('data.summary', 'Hűséges ügyfél, hívd fel a Q4 igényekről.');

        Http::assertSent(function (Request $request) {
            $payload = json_decode($request['messages'][0]['content'], true);

            return $request->hasHeader('x-api-key', 'test-key')
                && $request['model'] === 'test-model'
                && str_contains($request['system'], 'semmit ne találj ki')
                && ! array_key_exists('email', $payload['customer'])
                && ! array_key_exists('phone', $payload['customer']);
        });
    }

    public function test_falls_back_to_rules_when_the_ai_call_fails(): void
    {
        config(['erp.ai.key' => 'test-key', 'erp.ai.model' => 'test-model']);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);

        $customer = Customer::factory()->create();

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'rules');
    }

    public function test_summary_is_cached_until_the_timeline_changes(): void
    {
        config(['erp.ai.key' => 'test-key', 'erp.ai.model' => 'test-model']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Összefoglaló.']]])]);

        $customer = Customer::factory()->create();

        $this->getJson("/api/crm/customers/{$customer->id}/summary");
        $this->getJson("/api/crm/customers/{$customer->id}/summary");
        Http::assertSentCount(1);

        $this->postJson("/api/crm/customers/{$customer->id}/interactions", ['type' => 'note', 'subject' => 'Új info']);
        $this->getJson("/api/crm/customers/{$customer->id}/summary");
        Http::assertSentCount(2);
    }
}
