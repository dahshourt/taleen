<?php

namespace App\Services\KpiSubInitiative;

use App\Http\Repository\KpiSubInitiative\KpiSubInitiativeRepository;
use App\Models\KpiSubInitiative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KpiSubInitiativeService
{
    public function __construct(private KpiSubInitiativeRepository $kpiSubInitiativeRepository) {}

    public function getAll(): Collection
    {
        return $this->kpiSubInitiativeRepository->getAll();
    }

    public function getAllActive(): Collection
    {
        return $this->kpiSubInitiativeRepository->getAllActive();
    }

    public function find(int $id): ?KpiSubInitiative
    {
        return $this->kpiSubInitiativeRepository->find($id);
    }

    public function create(array $data): KpiSubInitiative
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $this->kpiSubInitiativeRepository->create($data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_by'] = Auth::id();

        return $this->kpiSubInitiativeRepository->update($data, $id);
    }

    public function toggleStatus(int $id): bool
    {
        return $this->kpiSubInitiativeRepository->toggleStatus($id);
    }
}
