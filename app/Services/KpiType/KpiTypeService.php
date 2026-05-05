<?php

namespace App\Services\KpiType;

use App\Http\Repository\KpiType\KpiTypeRepository;
use App\Models\KpiType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KpiTypeService
{
    public function __construct(private KpiTypeRepository $kpiTypeRepository) {}

    public function getAll(): Collection
    {
        return $this->kpiTypeRepository->getAll();
    }

    public function getAllActive(): Collection
    {
        return $this->kpiTypeRepository->getAllActive();
    }

    public function find(int $id): ?KpiType
    {
        return $this->kpiTypeRepository->find($id);
    }

    public function create(array $data): KpiType
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $this->kpiTypeRepository->create($data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_by'] = Auth::id();

        return $this->kpiTypeRepository->update($data, $id);
    }

    public function toggleStatus(int $id): bool
    {
        return $this->kpiTypeRepository->toggleStatus($id);
    }
}
