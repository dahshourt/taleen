<?php

namespace App\Services\LogViewer;

use App\Http\Repository\LogViewer\LogViewerRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class LogViewerService
{
    public function __construct(
        private LogViewerRepository $repository
    ) {}

    /**
     * Get filtered and paginated logs
     */
    public function getFilteredLogs(): LengthAwarePaginator
    {
        return $this->repository->getPaginatedLogs();
    }

    /**
     * Get a single log by ID
     */
    public function getLogById(int $id): ?object
    {
        return $this->repository->findById($id);
    }

    /**
     * Mark a log as resolved
     */
    public function resolveLog(int $id, int $userId): array
    {
        $result = $this->repository->markAsResolved($id, $userId);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Log marked as resolved successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to mark log as resolved.',
        ];
    }

    /**
     * Mark all similar logs as resolved
     */
    public function resolveAllSimilar(int $id, int $userId): array
    {
        $log = $this->repository->findById($id);

        if (! $log) {
            return [
                'success' => false,
                'message' => 'Log not found.',
            ];
        }

        $count = $this->repository->markAllSimilarAsResolved($log->log_hash, $userId);

        return [
            'success' => true,
            'message' => "Successfully marked {$count} similar log(s) as resolved.",
            'count' => $count,
        ];
    }

    /**
     * Delete a log entry
     */
    public function deleteLog(int $id): array
    {
        $result = $this->repository->delete($id);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Log deleted successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete log.',
        ];
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        return [
            'unresolved_errors' => $this->repository->getUnresolvedErrorsCount(),
            'available_levels' => $this->repository->getUniqueLevels(),
        ];
    }
}
