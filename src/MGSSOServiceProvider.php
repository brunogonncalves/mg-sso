<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class MGSSOServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
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

        $this->app->booted(function () {
            include __DIR__.'/routes.php';
        });

        if(isset($_SERVER['HTTP_HOST'])){
            $broker = $this->app->make('InspireSoftware\MGSSO\MGSSOBroker');
            $broker->attach();
            if(isset($broker->userinfo)) $broker->loginCurrentUser();
            
        }
    }
}
