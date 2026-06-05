<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        Paginator::useBootstrapFour();

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
