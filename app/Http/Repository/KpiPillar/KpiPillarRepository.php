<?php

namespace App\Http\Repository\KpiPillar;

use App\Models\KpiPillar;
use Illuminate\Support\Collection;

class KpiPillarRepository
{
    public function getAll(): Collection
    {
        return KpiPillar::orderBy('id', 'desc')->get();
    }

    public function getAllActive(): Collection
    {
        return KpiPillar::active()->orderBy('name', 'asc')->get();
    }

    public function create(array $data): KpiPillar
    {
        return KpiPillar::create($data);
    }

    public function update(array $data, int $id): bool
    {
        return KpiPillar::where('id', $id)->update($data);
    }

    public function find(int $id): ?KpiPillar
    {
        return KpiPillar::find($id);
    }

    public function toggleStatus(int $id): bool
    {
        $kpiPillar = $this->find($id);

        if (!$kpiPillar) {
            return false;
        }

        $kpiPillar->status = $kpiPillar->status === '1' ? '0' : '1';

        return $kpiPillar->save();
    }
}

