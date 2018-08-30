<?php

namespace BoxedCode\Laravel\SchemaRefresh;

use Illuminate\Support\ServiceProvider;

class RefreshSchemaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(RefreshSchema::class);
    }
}