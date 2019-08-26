# Description
Private SSO adapted to MundoGamer project

### installation
- `composer require inspiresoftware/mg-sso`
- `php artisan migrate --package=vendor/inspiresoftware/mg-sso/src/Migrations/add_network_id_field.php`
- Open config/app.php and add `InspireSoftware\MGSSO\MGSSOServiceProvider::class` to provider array.
  
#### .env
```
SSO_SERVER_URL=your-server-network-host
SSO_CLIENT_ID=your-client-id
SSO_CLIENT_SECRET=your-client-secret
```

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


# Enjoy the magic!