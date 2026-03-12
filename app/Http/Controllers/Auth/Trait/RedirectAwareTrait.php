<?php

namespace App\Http\Controllers\Auth\Trait;

use App\Models\User;
use App\Services\TenantOnboardingService;
use Illuminate\Support\Facades\Redirect;

trait RedirectAwareTrait
{
    protected function getRedirectUrl(?User $user): string
    {
        // Change this if you want to redirect to a different page after login

        if (! $user) {
            return route('home');
        }

        if (Redirect::getIntendedUrl() !== null && rtrim(Redirect::getIntendedUrl(), '/') !== rtrim((route('home')), '/')) {
            return Redirect::getIntendedUrl();
        }

        if ($user->is_admin) {
            return route('filament.admin.pages.dashboard');
        }

        if (app(TenantOnboardingService::class)->userRequiresOnboarding($user)) {
            return route('tenant-onboarding.show');
        }

        return route('dashboard');
    }
}
