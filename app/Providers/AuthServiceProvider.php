<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }

            if (isset($user->groups) && $user->groups->count() > 0) {
                foreach ($user->groups as $group) {
                    try {
                        if ($group->hasRole($ability)) {
                            return true;
                        }
                    } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
                        // Ignore
                    }

                    try {
                        if ($group->hasPermissionTo($ability)) {
                            return true;
                        }
                    } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                        // Ignore
                    }
                }
            }

            return null;
        });
    }
}
