<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fejlesztés közben azonnal kiderül az N+1 lekérdezés, az elgépelt
        // attribútum és a nem kitölthető mező. Élesben nem dob kivételt.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
