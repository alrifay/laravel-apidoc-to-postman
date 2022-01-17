<?php

namespace Alrifay\ApidocToPostman;

use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }
        $this->commands([
            GeneratePostmanCollectionCommand::class,
        ]);
    }
}
