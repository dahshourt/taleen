<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseStatus;
use App\Models\ReleaseStatusMapping;
use App\Models\Change_request;
use Illuminate\Support\Facades\Log;

class ReleaseStatusService
{
    /**
     * Calculate and update the release status based on its CRs.
     * The release status will be the LOWEST (least progressed) among all its CRs.
     */
    public function calculateAndUpdateStatus(Release $release): void
    {
        // Get all CRs for this release
        $crs = Change_request::where('release_name', $release->id)->get();

        if ($crs->isEmpty()) {
            // No CRs - set to first status (Planned)
            $this->setDefaultStatus($release);
            return;
        }

        $lowestOrder = PHP_INT_MAX;
        $lowestStatusId = null;

        foreach ($crs as $cr) {
            // Get the CR's current status name
            $crStatusRecord = $cr->currentStatusRel;
            if (!$crStatusRecord || !$crStatusRecord->status) {
                Log::warning('CR has no active status, skipping for release calculation', [
                    'cr_id' => $cr->id,
                    'cr_no' => $cr->cr_no,
                    'release_id' => $release->id,
                ]);
                continue;
            }

            $crStatusName = $crStatusRecord->status->status_name;

            // Find the mapping for this CR status
            $mapping = ReleaseStatusMapping::where('cr_status_name', $crStatusName)->first();
            
            if ($mapping && $mapping->releaseStatus) {
                $releaseStatus = $mapping->releaseStatus;
                
                if ($releaseStatus->display_order < $lowestOrder) {
                    $lowestOrder = $releaseStatus->display_order;
                    $lowestStatusId = $releaseStatus->id;
                }
            } else {
                Log::info('No release status mapping found for CR status', [
                    'cr_status_name' => $crStatusName,
                    'cr_id' => $cr->id,
                    'release_id' => $release->id,
                ]);
            }
        }

        // Update release status if we found one
        if ($lowestStatusId !== null) {
            $release->release_status_id = $lowestStatusId;
            $release->save();

            Log::info('Release status updated based on CR statuses', [
                'release_id' => $release->id,
                'new_release_status_id' => $lowestStatusId,
            ]);
        }
    }

    /**
     * Set default "Planned" status (first status by display_order).
     */
    public function setDefaultStatus(Release $release): void
    {
        $firstStatus = ReleaseStatus::where('active', true)
            ->orderBy('display_order')
            ->first();

        if ($firstStatus) {
            $release->release_status_id = $firstStatus->id;
            $release->save();

            Log::info('Release set to default status (Planned)', [
                'release_id' => $release->id,
                'status_id' => $firstStatus->id,
                'status_name' => $firstStatus->name,
            ]);
        }
    }

    /**
     * Recalculate status for a release by ID.
     */
    public function recalculateForRelease(int $releaseId): void
    {
        $release = Release::find($releaseId);
        if ($release) {
            $this->calculateAndUpdateStatus($release);
        } else {
            Log::warning('Release not found for status recalculation', [
                'release_id' => $releaseId,
            ]);
        }
    }

    /**
     * Recalculate status when a CR's release assignment changes.
     */
    public function handleCrReleaseChanged(?int $oldReleaseId, ?int $newReleaseId): void
    {
        // Recalculate old release if CR was removed
        if ($oldReleaseId) {
            $this->recalculateForRelease($oldReleaseId);
        }

        // Recalculate new release if CR was added
        if ($newReleaseId) {
            $this->recalculateForRelease($newReleaseId);
        }
    }

    /**
     * Recalculate status when a CR's status changes.
     */
    public function handleCrStatusChanged(Change_request $cr): void
    {
        if ($cr->release_name) {
            $this->recalculateForRelease($cr->release_name);
        }
    }
}
