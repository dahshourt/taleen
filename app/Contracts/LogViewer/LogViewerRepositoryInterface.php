<?php

namespace App\Contracts\LogViewer;

use App\Models\LogViewer;
use Illuminate\Pagination\LengthAwarePaginator;

interface LogViewerRepositoryInterface
{
    public function getPaginatedLogs(): LengthAwarePaginator;

    public function findById(int $id): ?LogViewer;

    public function markAsResolved(int $id, int $userId): bool;

    public function markAllSimilarAsResolved(string $logHash, int $userId): int;

    public function delete(int $id): bool;

    public function getUnresolvedErrorsCount(): int;

    public function getUniqueLevels(): array;
}
