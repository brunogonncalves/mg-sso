<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\ServiceProvider;

class MGSSOServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // $this->app->make('InpireSoftware\MGSSO\Controllers\MGSSOController');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
