<?php

namespace Tests\Feature\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Console\FollowUpCommand;
use Modules\Crm\Models\Customer;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_follow_up_only_for_dormant_customers(): void
    {
        $dormant = Customer::factory()->create();
        Order::factory()->for($dormant)->placedDaysAgo(20)->create();

        $active = Customer::factory()->create();
        Order::factory()->for($active)->placedDaysAgo(20)->create();
        Order::factory()->for($active)->placedDaysAgo(2)->create();

        $recentlyCalled = Customer::factory()->create(['last_contacted_at' => now()->subDay()]);
        Order::factory()->for($recentlyCalled)->placedDaysAgo(20)->create();

        $cancelledOnly = Customer::factory()->create();
        Order::factory()->for($cancelledOnly)->placedDaysAgo(20)->create(['status' => 'cancelled']);

        $this->artisan('crm:follow-ups', ['--days' => 7])->expectsOutput('1 utókövetési teendő létrehozva.');

        $this->assertSame(1, $dormant->openTasks()->count());
        $this->assertSame(0, $active->openTasks()->count());
        $this->assertSame(0, $recentlyCalled->openTasks()->count());
        $this->assertSame(0, $cancelledOnly->openTasks()->count());
    }

    public function test_is_idempotent(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->placedDaysAgo(30)->create();

        $this->artisan('crm:follow-ups');
        $this->artisan('crm:follow-ups')->expectsOutput('0 utókövetési teendő létrehozva.');

        $this->assertSame(1, $customer->openTasks()->where('subject', FollowUpCommand::SUBJECT)->count());
    }

    public function test_completing_the_task_via_api(): void
    {
        $customer = Customer::factory()->create();
        $task = $customer->interactions()->create(['type' => 'task', 'subject' => 'Visszahívás', 'due_at' => now(), 'occurred_at' => now()]);

        $this->patchJson("/api/crm/customers/{$customer->id}/interactions/{$task->id}/complete")
            ->assertOk()->assertJsonPath('data.completed_at', fn ($v) => $v !== null);

        $this->assertSame(0, $customer->openTasks()->count());
    }

    public function test_logging_a_call_updates_last_contacted_at(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson("/api/crm/customers/{$customer->id}/interactions", [
            'type' => 'call', 'subject' => 'Ajánlat megbeszélése',
        ])->assertCreated();

        $this->assertNotNull($customer->fresh()->last_contacted_at);
    }

    public function test_order_type_cannot_be_logged_by_hand(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson("/api/crm/customers/{$customer->id}/interactions", ['type' => 'order', 'subject' => 'Hamis'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }
}
