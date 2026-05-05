<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StatusConfigService
{
    /**
     * Load default status IDs (without suffix)
     */
    public static function loadStatusIds(): array
    {
        return self::mapStatuses('');
    }

    /**
     * Load KAM status IDs (with ' kam' suffix)
     */
    public static function loadStatusIdsKam(): array
    {
        return self::mapStatuses(' kam');
    }

    /**
     * Load status IDs by custom suffix
     */
    public static function loadStatusIdsBySuffix(string $suffix): array
    {
        return self::mapStatuses($suffix);
    }

    /**
     * Map status names to IDs from database
     *
     * @param string $suffix The suffix to append to status names (e.g., ' kam', ' promo')
     * @return array Array of status key => status ID mappings
     */
    /**
     * Map status names to IDs from database
     *
     * @param string $suffix The suffix to append to status names (e.g., ' kam', ' promo')
     * @return array Array of status key => status ID mappings
     */
    private static function mapStatuses(string $suffix): array
    {
        // Use a cache key based on the suffix
        $cacheKey = 'status_config_ids_' . md5($suffix);

        // Try to get from cache first (cache for 1 hour instead of 24)
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($suffix) {
            // Check if we're in a context where we can access the database
            if (!self::canAccessDatabase()) {
                return [];
            }

            $names = self::getStatusNameMappings();
            $result = [];

            try {
                foreach ($names as $key => $baseName) {
                    $searchName = $baseName . $suffix;

                    // Use case-insensitive comparison
                    $status = DB::table('statuses')
                        ->whereRaw('LOWER(status_name) = ?', [strtolower($searchName)])
                        ->where('active', '1')
                        ->first();

                    if ($status) {
                        $result[$key] = $status->id;
                    }
                }

                return $result;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Check if we can access the database
     */
    private static function canAccessDatabase(): bool
    {
        try {
            // Check if app is bound and database connection is available
            if (!function_exists('app') || !app()->bound('db')) {
                return false;
            }

            // Try a simple query to verify database is accessible
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Safe logging without using facades directly
     */
    private static function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            if (function_exists('app') && app()->bound('log')) {
                app('log')->{$level}($message, $context);
            } else {
                // Fallback to PHP's error_log in very early boot
                error_log(strtoupper($level) . ': ' . $message . ' ' . json_encode($context));
            }
        } catch (\Throwable $e) {
            // Swallow logging errors – never break the app because of logging
        }
    }

    // === rest of your methods (unchanged) ===

    private static function getStatusNameMappings(): array
    {
        return [
            'technical_estimation' => 'Technical estimation',
            'pending_implementation' => 'Pending implementation',
            'technical_implementation' => 'Technical Implementation',
            'pending_production_deployment' => 'Pending production Deployment Promo',
            'production_deployment' => 'Deploy In Progress',
            'business_approval' => 'Business Approval',
            'business_approval_kam' => 'Business Approval',
            'pending_cab' => 'Pending CAB',
            'pending_cab_kam' => 'Pending CAB',
            'pending_cab_proceed' => 'Pending Test Cases Rework',
            'pending_cab_review' => 'Pending CAB',
            'set_kickoff' => 'confirmed',
            'cr_manager_review' => 'Review CD_CR',
            'design_phase' => 'Design Test Case Approval',
            'development_ready' => 'Pending Create Test Cases',
            'development_in_progress' => 'Business Test Case Approval',
            'pending_uat' => 'Pending UAT',
            'testing_phase' => 'UAT In Progress',
            'uat_phase' => 'CR Production Deployment Pre -requisites',
            'business_test_case_approval' => 'Business Test Case Approval',
            'business_test_case_approval_kam' => 'Business Test Case Approval',
            'business_uat_sign_off' => 'Business UAT Sign Off',
            'business_uat_sign_off_kam' => 'Business UAT Sign Off',
            'pending_business' => 'Pending Business',
            'pending_business_kam' => 'Pending Business',
            'pending_business_feedback' => 'Pending Business Feedback',
            'pending_business_feedback_kam' => 'Pending Business Feedback',
            'approved_implementation_plan' => 'Approved Implementation Plan',
            'pending_cd_analysis' => 'Pending CD Analysis',
            'pending_stage_deployment_in_house' => 'Pending Stage Deployment',
            'pending_production_deployment_in_house' => 'Pending Production Deployment',
            'Reject' => 'Reject',
            'Reject_kam' => 'Reject',
            'Cancel' => 'Cancel',
            'Cancel_kam' => 'Cancel',
            'Closed' => 'Closed',
            'Delivered' => 'Delivered',
            'pending_design' => 'Pending Design',
            'division_manager_approval' => 'Division Manager Approval',
            'division_manager_approval_kam' => 'Division Manager Approval',
            'business_analysis' => 'Business Analysis',
            'business_analysis_kam' => 'Business Analysis',
            'business_feedback' => 'Business Feedback',
            'pending_cab_approval' => 'Pending CAB Approval',
            'pending_update_cr_doc' => 'Pending Update CR Doc',
            'request_vendor_mds' => 'Request Vendor MDS',
            'pending_agreed_business' => 'Pending Agreed Scope Approval-Business',
            'pending_agreed_business_kam' => 'Pending Agreed Scope Approval-Business',
            'prototype_approval_business' => 'Prototype Approval-Business',
            'pending_operation_approvals' => 'Pending Operation Approvals',
            'cr_directors_approvals' => 'CR Directors Approvals',
            'pending_testing' => 'Pending Testing',
            'pending_operational_dm_capacity_approval' => 'Pending Operation DM and Capacity approval',
            'pending_deployment_approval' => 'Pending Deployment Approval',
            'production_deployment_in_progress' => 'Production Deployment In-Progress',
            'pending_uat_vendor' => 'Pending UAT (vendor)',
            'pending_business_confirmation' => 'Pending Business Confirmation',
            'sr_business_fb' => 'SR Business FB',
        ];
    }

    public static function getStatusId(string $key, string $suffix = ''): ?int
    {
        $statuses = self::loadStatusIdsBySuffix($suffix);
        return $statuses[$key] ?? null;
    }

    public static function loadAllStatusVariations(array $suffixes = ['', ' kam']): array
    {
        $result = [];

        foreach ($suffixes as $suffix) {
            $key = $suffix === '' ? 'default' : trim($suffix);
            $result[$key] = self::mapStatuses($suffix);
        }

        return $result;
    }

    public static function validateStatusIds(string $suffix = ''): array
    {
        $statusIds = self::mapStatuses($suffix);
        $requiredKeys = array_keys(self::getStatusNameMappings());

        $missing = [];
        foreach ($requiredKeys as $key) {
            if (!isset($statusIds[$key])) {
                $missing[] = $key;
            }
        }

        return [
            'valid' => empty($missing),
            'missing_keys' => $missing,
            'found_count' => count($statusIds),
            'total_count' => count($requiredKeys),
        ];
    }

    public static function getStatusNameByKey(string $key): ?string
    {
        $mappings = self::getStatusNameMappings();
        return $mappings[$key] ?? null;
    }

    public static function hasStatusKey(string $key): bool
    {
        $mappings = self::getStatusNameMappings();
        return isset($mappings[$key]);
    }

    public static function debugKamStatuses()
    {
        try {
            $statuses = DB::table('statuses')
                ->where('status_name', 'like', '%kam%')
                ->where('active', '1')
                ->pluck('status_name', 'id')
                ->toArray();

            if (empty($statuses)) {
                return 'No statuses with "kam" found in the database.';
            }

            return $statuses;
        } catch (\Throwable $e) {
            return 'Error fetching statuses: ' . $e->getMessage();
        }
    }
}


