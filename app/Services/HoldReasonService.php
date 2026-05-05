<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Repository\HoldReason\HoldReasonRepository;
use App\Models\HoldReason;
use Illuminate\Support\Collection;

class HoldReasonService
{
    protected $holdReasonRepository;

    public function __construct(HoldReasonRepository $holdReasonRepository)
    {
        $this->holdReasonRepository = $holdReasonRepository;
    }

    public function getAllHoldReasons(): Collection
    {
        return $this->holdReasonRepository->getAll();
    }

    public function getActiveHoldReasons(): Collection
    {
        return $this->holdReasonRepository->getAllActive();
    }

    public function findHoldReason($id): ?HoldReason
    {
        return $this->holdReasonRepository->find($id);
    }

    public function createHoldReason(array $data): HoldReason
    {
        return $this->holdReasonRepository->create($data);
    }

    public function updateHoldReason(array $data, $id): bool
    {
        $result = $this->holdReasonRepository->update($data, $id);

        return $result > 0 || $result === 0; // Return true if update was successful (even if no rows changed)
    }
}
