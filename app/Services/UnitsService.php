<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Repository\Units\UnitsRepository;
use App\Models\Unit;

class UnitsService
{
    protected $unitsRepository;

    public function __construct(UnitsRepository $unitsRepository)
    {
        $this->unitsRepository = $unitsRepository;
    }

    public function getAllUnits(bool $paginated = false)
    {
        return $this->unitsRepository->getAll($paginated);
    }

    public function findUnit($id): Unit
    {
        return $this->unitsRepository->find($id);
    }

    public function createUnit(array $data): Unit
    {
        return $this->unitsRepository->create($data);
    }

    public function updateUnit(array $data, $id): Unit
    {
        return $this->unitsRepository->update($data, $id);
    }

    public function updateUnitStatus($id): bool
    {
        $unit = $this->findUnit($id);

        $new_status = (int) ! $unit->status;

        return $this->unitsRepository->updateStatus((string) $new_status, $id);
    }
}
