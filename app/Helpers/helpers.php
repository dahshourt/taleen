<?php

// Add this to: app/Helpers/helpers.php (create if doesn't exist)

use App\Services\StatusConfigService;

if (!function_exists('getStatusId')) {
    /**
     * Get status ID from config or database
     * 
     * @param string $key The status key (e.g., 'business_approval')
     * @param string $suffix The suffix ('' for default, ' kam' for KAM)
     * @return int|null
     */
    function getStatusId(string $key, string $suffix = ''): ?int
    {
        // First, try to get from config
        $configKey = $suffix === ' kam' ? 'change_request.status_ids_kam' : 'change_request.status_ids';
        $statusIds = config($configKey, []);
        
        // If found in config, return it
        if (isset($statusIds[$key])) {
            return $statusIds[$key];
        }
        
        // If config is empty or key not found, load directly from database
        return StatusConfigService::getStatusId($key, $suffix);
    }
}

if (!function_exists('getAllStatusIds')) {
    /**
     * Get all status IDs
     * 
     * @param string $suffix The suffix ('' for default, ' kam' for KAM)
     * @return array
     */
    function getAllStatusIds(string $suffix = ''): array
    {
        // First, try to get from config
        $configKey = $suffix === ' kam' ? 'change_request.status_ids_kam' : 'change_request.status_ids';
        $statusIds = config($configKey, []);
        
        // If config has data, return it
        if (!empty($statusIds)) {
            return $statusIds;
        }
        
        // If config is empty, load directly from database
        return StatusConfigService::loadStatusIdsBySuffix($suffix);
    }
}