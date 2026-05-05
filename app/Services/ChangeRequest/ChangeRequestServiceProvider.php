<?php

namespace App\Services\ChangeRequest;

use Illuminate\Support\ServiceProvider;
use App\Services\StatusConfigService;

class ChangeRequestServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Always reload status IDs from database after app boots
        if (! $this->shouldLoadDynamicStatuses()) {
            return;
        }

        $this->loadDynamicStatusIds();
    }

    /**
     * Determine if we should load dynamic statuses.
     */
    protected function shouldLoadDynamicStatuses(): bool
    {
        // Don't load during config:cache or config:clear
        if ($this->app->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? '';

            // Skip for these commands
            $skipCommands = [
                'config:cache',
                'config:clear',
                'package:discover',
            ];

            foreach ($skipCommands as $skipCommand) {
                if (str_contains($command, $skipCommand)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Load dynamic status IDs from database and override config
     * Loads BOTH default and KAM status IDs separately.
     */
    protected function loadDynamicStatusIds(): void
    {
        try {
            // Load default status IDs from database
            $statusIds = StatusConfigService::loadStatusIds();

            if (! empty($statusIds)) {
                // Use container instead of Config facade
                $this->app['config']->set('change_request.status_ids', $statusIds);
            }

            // Load KAM status IDs from database
            $kamStatusIds = StatusConfigService::loadStatusIdsKam();

            if (! empty($kamStatusIds)) {
                $this->app['config']->set('change_request.status_ids_kam', $kamStatusIds);
            }

        } catch (\Throwable $e) {
            // Use container logger instead of Log facade
            if ($this->app['config']->get('app.debug')) {
                $this->app['log']->warning(
                    'Failed to load dynamic status IDs: ' . $e->getMessage()
                );
            }
        }
    }
}
