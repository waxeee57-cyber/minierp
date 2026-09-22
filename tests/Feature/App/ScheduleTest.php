<?php

namespace Tests\Feature\App;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_modules_register_their_scheduled_jobs(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($e) => [trim(str($e->command)->after('artisan')->replace(["'", '"'], '')) => $e]);

        $this->assertSame('0 7 * * 1-5', $events['inventory:low-stock-alert']->expression);
        $this->assertSame('0 8 * * *', $events['crm:follow-ups']->expression);
        $this->assertTrue($events['crm:follow-ups']->withoutOverlapping);
        $this->assertTrue($events['crm:follow-ups']->onOneServer, 'Több szerveren is csak egyszer fusson.');
    }
}
