<?php

namespace App\Http\Repository\Units;

use App\Contracts\Units\UnitsRepositoryInterface;
use App\Models\Unit;

class UnitsRepository implements UnitsRepositoryInterface
{
    public function getAll(bool $paginate = false)
    {
        $units = Unit::query();

        if ($paginate) {
            return $units->paginate();
        }

        return $units->get();
    }

    public function find($id): Unit
    {
        return Unit::find($id);
    }

    public function create($request): Unit
    {
        return Unit::create($request);
    }

    public function update($request, $id): Unit
    {
        $unit = Unit::findOrFail($id);
        $unit->update($request);

        return $unit;
    }

    public function updateStatus($status, $id): bool
    {
        return Unit::where('id', $id)->update(['status' => $status]);
    }
}
