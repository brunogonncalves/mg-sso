<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

use InspireSoftware\MGSSO\Console\MGSSOIntegrationCommand;

class MGSSOServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->publishes([
            __DIR__ . '/MGSSOConfig.php' => config_path('mgsso.php'),
        ], 'config');
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
        $this->loadViewsFrom(__DIR__.'/Views', 'mgsso');

        $this->app->booted(function () {
            include __DIR__.'/routes.php';
        });

        if ($this->app->runningInConsole()) {
            
            $this->commands([
                MGSSOIntegrationCommand::class,
            ]);

        } else {

            $broker = $this->app->make('InspireSoftware\MGSSO\MGSSOBroker');
            $broker->attach();
            $broker->initialVerify();

        }
        
    }
}
