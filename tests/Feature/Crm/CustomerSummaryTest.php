<?php

namespace Tests\Feature\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Ai\CustomerBriefAgent;
use Modules\Crm\Models\Customer;
use Modules\Orders\Models\Order;
use RuntimeException;
use Tests\TestCase;

class CustomerSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function enableAi(): void
    {
        config(['erp.ai.provider' => 'anthropic', 'ai.providers.anthropic.key' => 'test-key']);
    }

    public function test_rule_based_summary_without_api_key(): void
    {
        config(['ai.providers.anthropic.key' => null]);
        $customer = Customer::factory()->create(['name' => 'Teszt Elek']);
        Order::factory()->for($customer)->placedDaysAgo(10)->create(['status' => 'paid', 'total' => 125_000]);

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'rules')
            ->assertJsonPath('data.next_action', fn ($a) => str_contains($a, 'Hívd fel'))
            ->assertJsonPath('data.summary', fn ($s) => str_contains($s, '1 rendelés') && str_contains($s, "125\u{00A0}000 Ft") && str_contains($s, 'érdemes felhívni'));
    }

    public function test_ai_summary_uses_structured_output_and_no_contact_details(): void
    {
        $this->enableAi();
        CustomerBriefAgent::fake([['summary' => 'Hűséges ügyfél, havonta rendel.', 'next_action' => 'Ajánlj Q4 keretszerződést.']]);

        $customer = Customer::factory()->create(['email' => 'titkos@example.com', 'phone' => '+36 30 000 0000']);

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.summary', 'Hűséges ügyfél, havonta rendel.')
            ->assertJsonPath('data.next_action', 'Ajánlj Q4 keretszerződést.');

        CustomerBriefAgent::assertPrompted(fn ($prompt) => ! str_contains($prompt->prompt, 'titkos@example.com')
            && ! str_contains($prompt->prompt, '+36 30')
            && str_contains($prompt->prompt, '"orders"'));
    }

    public function test_falls_back_to_rules_when_the_ai_call_fails(): void
    {
        $this->enableAi();
        CustomerBriefAgent::fake(fn () => throw new RuntimeException('overloaded'));

        $customer = Customer::factory()->create();

        $this->getJson("/api/crm/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.source', 'rules');
    }

    public function test_summary_is_cached_until_the_timeline_changes(): void
    {
        $this->enableAi();
        CustomerBriefAgent::fake([
            ['summary' => 'Első.', 'next_action' => 'X'],
            ['summary' => 'Második.', 'next_action' => 'Y'],
        ]);

        $customer = Customer::factory()->create();

        $this->getJson("/api/crm/customers/{$customer->id}/summary")->assertJsonPath('data.summary', 'Első.');
        $this->getJson("/api/crm/customers/{$customer->id}/summary")->assertJsonPath('data.summary', 'Első.');
        CustomerBriefAgent::assertPromptedTimes(1);

        $this->postJson("/api/crm/customers/{$customer->id}/interactions", ['type' => 'note', 'subject' => 'Új info']);
        $this->getJson("/api/crm/customers/{$customer->id}/summary")->assertJsonPath('data.summary', 'Második.');
        CustomerBriefAgent::assertPromptedTimes(2);
    }
}
