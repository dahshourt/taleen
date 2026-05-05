<?php

namespace App\Services\KpiInitiative;

use App\Http\Repository\KpiInitiative\KpiInitiativeRepository;
use App\Models\KpiInitiative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KpiInitiativeService
{
    public function __construct(private KpiInitiativeRepository $kpiInitiativeRepository) {}

    public function getAll(): Collection
    {
        return $this->kpiInitiativeRepository->getAll();
    }

    public function getAllActive(): Collection
    {
        return $this->kpiInitiativeRepository->getAllActive();
    }

    public function find(int $id): ?KpiInitiative
    {
        return $this->kpiInitiativeRepository->find($id);
    }

    public function create(array $data): KpiInitiative
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $this->kpiInitiativeRepository->create($data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_by'] = Auth::id();

        return $this->kpiInitiativeRepository->update($data, $id);
    }

    public function toggleStatus(int $id): bool
    {
        return $this->kpiInitiativeRepository->toggleStatus($id);
    }
}
