<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Laragear\TwoFactor\Facades\Auth2FA;

class LoginService
{
    public function attempt(array $credentials, bool $remember): bool
    {
        if (config('app.two_factor_auth_enabled')) {
            return Auth2FA::attempt($credentials, $remember);
        }

        return Auth::guard()->attempt($credentials, $remember);
    }
}
