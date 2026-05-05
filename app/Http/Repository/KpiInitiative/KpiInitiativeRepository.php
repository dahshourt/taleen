<?php

namespace App\Http\Repository\KpiInitiative;

use App\Models\KpiInitiative;
use Illuminate\Support\Collection;

class KpiInitiativeRepository
{
    public function getAll(): Collection
    {
        return KpiInitiative::with('pillar')->orderBy('id', 'desc')->get();
    }

    public function getAllActive(): Collection
    {
        return KpiInitiative::active()->orderBy('name', 'asc')->get();
    }

    public function create(array $data): KpiInitiative
    {
        return KpiInitiative::create($data);
    }

    public function update(array $data, int $id): bool
    {
        return KpiInitiative::where('id', $id)->update($data);
    }

    public function find(int $id): ?KpiInitiative
    {
        return KpiInitiative::with('pillar')->find($id);
    }

    public function toggleStatus(int $id): bool
    {
        $kpiInitiative = $this->find($id);

        if (!$kpiInitiative) {
            return false;
        }

        $kpiInitiative->status = $kpiInitiative->status === '1' ? '0' : '1';

        return $kpiInitiative->save();
    }
}

