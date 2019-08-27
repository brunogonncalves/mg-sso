# Description
Private SSO adapted to MundoGamer project

### installation
- `composer require inspiresoftware/mg-sso`
- Open config/app.php and add `InspireSoftware\MGSSO\MGSSOServiceProvider::class` to provider array.
- Execute `php artisan migrate`
  
#### .env
```
SSO_SERVER_URL=your-server-network-host
SSO_CLIENT_ID=your-client-id
SSO_CLIENT_SECRET=your-client-secret
```

#### SSOUser trait and fillable
```
<?php namespace App\Models;

use InspireSoftware\MGSSO\Traits\SSOUser;

class User extends Authenticatable
{
    // add trait
    use SSOUser;

    protected $fillable = [
        'network_id', // don't forget to add this field
    ]

}
```

### Update users
 Don't forget to run `php artisan mgsso:integration` this command will find all users and populate `network_id` column in user based on network user


# Enjoy the magic!