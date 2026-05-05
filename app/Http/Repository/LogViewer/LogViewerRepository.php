<?php

namespace App\Http\Repository\LogViewer;

use App\Contracts\LogViewer\LogViewerRepositoryInterface;
use App\Models\LogViewer;
use Illuminate\Pagination\LengthAwarePaginator;

class LogViewerRepository implements LogViewerRepositoryInterface
{
    /**
     * Get paginated logs with filters
     */
    public function getPaginatedLogs(): LengthAwarePaginator
    {
        return LogViewer::with('solver')
            ->filters()
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Find log by ID
     */
    public function findById(int $id): ?LogViewer
    {
        return LogViewer::with('solver')->find($id);
    }

    /**
     * Mark a single log as resolved
     */
    public function markAsResolved(int $id, int $userId): bool
    {
        $log = $this->findById($id);

        if (! $log) {
            return false;
        }

        return $log->update([
            'solved' => true,
            'solved_by' => $userId,
            'solved_at' => now(),
        ]);
    }

    /**
     * Mark all logs with the same hash as resolved
     */
    public function markAllSimilarAsResolved(string $logHash, int $userId): int
    {
        return LogViewer::where('log_hash', $logHash)
            ->where('solved', false)
            ->update([
                'solved' => true,
                'solved_by' => $userId,
                'solved_at' => now(),
            ]);
    }

    /**
     * Delete a log entry
     */
    public function delete(int $id): bool
    {
        $log = $this->findById($id);

        if (! $log) {
            return false;
        }

        return $log->delete();
    }

    /**
     * Get count of unresolved errors
     */
    public function getUnresolvedErrorsCount(): int
    {
        return LogViewer::toBase()->where('level_name', 'ERROR')
            ->distinct('log_hash')
            ->where('solved', false)
            ->count();
    }

    /**
     * Get unique log levels
     */
    public function getUniqueLevels(): array
    {
        return LogViewer::toBase()
            ->distinct()
            ->pluck('level_name')
            ->toArray();
    }
}
