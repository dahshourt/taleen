<?php

namespace App\Http\Repository\KpiType;

use App\Models\KpiType;
use Illuminate\Support\Collection;

class KpiTypeRepository
{
    public function getAll(): Collection
    {
        return KpiType::orderBy('id', 'desc')->get();
    }

    public function getAllActive(): Collection
    {
        return KpiType::active()->orderBy('name', 'asc')->get();
    }

    public function create(array $data): KpiType
    {
        return KpiType::create($data);
    }

    public function update(array $data, int $id): bool
    {
        return KpiType::where('id', $id)->update($data);
    }

    public function find(int $id): ?KpiType
    {
        return KpiType::find($id);
    }

    public function toggleStatus(int $id): bool
    {
        $kpiType = $this->find($id);

        if (!$kpiType) {
            return false;
        }

        $kpiType->status = $kpiType->status === '1' ? '0' : '1';

        return $kpiType->save();
    }
}

