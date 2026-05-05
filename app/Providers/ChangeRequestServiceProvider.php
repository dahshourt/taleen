<?php

namespace App\Providers;

use App\Contracts\ChangeRequest\ChangeRequestRepositoryInterface;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Services\ChangeRequest\ChangeRequestCreationService;
use App\Services\ChangeRequest\ChangeRequestEstimationService;
use App\Services\ChangeRequest\ChangeRequestSchedulingService;
use App\Services\ChangeRequest\ChangeRequestSearchService;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Services\ChangeRequest\ChangeRequestUpdateService;
use App\Services\ChangeRequest\ChangeRequestValidationService;
use Illuminate\Support\ServiceProvider;

class ChangeRequestServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register all Change Request services as singletons
        $this->app->singleton(ChangeRequestCreationService::class);
        $this->app->singleton(ChangeRequestUpdateService::class);
        $this->app->singleton(ChangeRequestStatusService::class);
        $this->app->singleton(ChangeRequestSchedulingService::class);
        $this->app->singleton(ChangeRequestSearchService::class);
        $this->app->singleton(ChangeRequestValidationService::class);
        $this->app->singleton(ChangeRequestEstimationService::class);

        // Bind repository interface to implementation
        $this->app->bind(ChangeRequestRepositoryInterface::class, ChangeRequestRepository::class);

        // Register repository as singleton
        $this->app->singleton(ChangeRequestRepository::class, function ($app) {
            return new ChangeRequestRepository(
                $app->make(ChangeRequestCreationService::class),
                $app->make(ChangeRequestUpdateService::class),
                $app->make(ChangeRequestStatusService::class),
                $app->make(ChangeRequestSchedulingService::class),
                $app->make(ChangeRequestSearchService::class),
                $app->make(ChangeRequestValidationService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/change_request.php' => config_path('change_request.php'),
        ], 'change-request-config');

        // Load migrations if needed
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Register event listeners for Change Request events
     */
    protected function registerEventListeners(): void
    {
        // Register event listeners if using events
        /*
        Event::listen(
            \App\Events\ChangeRequest\ChangeRequestCreated::class,
            \App\Listeners\ChangeRequest\SendCreationNotification::class
        );

        Event::listen(
            \App\Events\ChangeRequest\ChangeRequestStatusUpdated::class,
            \App\Listeners\ChangeRequest\SendStatusUpdateNotification::class
        );

        Event::listen(
            \App\Events\ChangeRequest\ChangeRequestAssigned::class,
            \App\Listeners\ChangeRequest\SendAssignmentNotification::class
        );
        */
    }
}
