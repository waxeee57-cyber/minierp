<?php

namespace Modules\Crm\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Crm\Ai\CustomerBriefAgent;
use Modules\Crm\Console\FollowUpCommand;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Contracts\Timeline;
use Modules\Crm\Services\EloquentCustomerDirectory;
use Modules\Crm\Services\EloquentTimeline;
use Modules\Crm\Services\Summary\AiSummarizer;
use Modules\Crm\Services\Summary\CustomerSummarizer;
use Modules\Crm\Services\Summary\RuleBasedSummarizer;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Timeline::class, EloquentTimeline::class);
        $this->app->singleton(CustomerDirectory::class, EloquentCustomerDirectory::class);

        // AI csak akkor, ha a kiválasztott szolgáltatóhoz van kulcs; különben szabályalapú.
        $this->app->bind(CustomerSummarizer::class, fn ($app) => CustomerBriefAgent::enabled()
            ? $app->make(AiSummarizer::class)
            : $app->make(RuleBasedSummarizer::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([FollowUpCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('crm:follow-ups')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
        });
    }
}
