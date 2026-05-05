<?php

namespace App\Http\Repository\KpiSubInitiative;

use App\Models\KpiSubInitiative;
use Illuminate\Support\Collection;

class KpiSubInitiativeRepository
{
    public function getAll(): Collection
    {
        return KpiSubInitiative::with('initiative')->orderBy('id', 'desc')->get();
    }

    public function getAllActive(): Collection
    {
        return KpiSubInitiative::active()->orderBy('name', 'asc')->get();
    }

    public function create(array $data): KpiSubInitiative
    {
        return KpiSubInitiative::create($data);
    }

    public function update(array $data, int $id): bool
    {
        return KpiSubInitiative::where('id', $id)->update($data);
    }

    public function find(int $id): ?KpiSubInitiative
    {
        return KpiSubInitiative::with('initiative')->find($id);
    }

    public function toggleStatus(int $id): bool
    {
        $kpiSubInitiative = $this->find($id);

        if (!$kpiSubInitiative) {
            return false;
        }

        $kpiSubInitiative->status = $kpiSubInitiative->status === '1' ? '0' : '1';

        return $kpiSubInitiative->save();
    }
}

