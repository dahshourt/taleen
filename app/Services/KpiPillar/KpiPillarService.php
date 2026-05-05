<?php

namespace App\Services\KpiPillar;

use App\Http\Repository\KpiPillar\KpiPillarRepository;
use App\Models\KpiPillar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KpiPillarService
{
    public function __construct(private KpiPillarRepository $kpiPillarRepository) {}

    public function getAll(): Collection
    {
        return $this->kpiPillarRepository->getAll();
    }

    public function getAllActive(): Collection
    {
        return $this->kpiPillarRepository->getAllActive();
    }

    public function find(int $id): ?KpiPillar
    {
        return $this->kpiPillarRepository->find($id);
    }

    public function create(array $data): KpiPillar
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $this->kpiPillarRepository->create($data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_by'] = Auth::id();

        return $this->kpiPillarRepository->update($data, $id);
    }

    public function toggleStatus(int $id): bool
    {
        return $this->kpiPillarRepository->toggleStatus($id);
    }
}
