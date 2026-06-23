<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'identity';
<<<<<<< HEAD
    protected $table = 'oriotel1_identity.personal_access_tokens';
=======
>>>>>>> import/master
}
