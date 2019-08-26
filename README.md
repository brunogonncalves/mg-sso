#Description
Private SSO adapted to MundoGamer project

### installation
- `composer require inspiresoftware/mg-sso`
- `php artisan migrate --package=vendor/inspiresoftware/mg-sso/src/Migrations/add_network_id_field.php`
- Open config/app.php and add `InspireSoftware\MGSSO\MGSSOServiceProvider::class` to provider array.
- Open your User.php model and add the trait `InspireSoftware\MGSSO\Traits\SSOUser`.
  
#### SSOUser trait
```
<?php namespace App\Models;

use InspireSoftware\MGSSO\Traits\SSOUser;

class User extends Authenticatable
{
    // add trait
    use SSOUser;

}

```


#Enjoy the magic!