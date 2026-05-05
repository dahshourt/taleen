<?php

namespace App\Http\Repository\Units;

use App\Contracts\Units\UnitRepositoryInterface;
// declare Entities
use App\Models\Unit;
use Illuminate\Support\Collection;

class UnitRepository implements UnitRepositoryInterface
{
    public function getAll()
    {
        return Unit::all();
    }

    public function getAllActive(): Collection
    {
        return Unit::active()->get();
    }

    public function create($request)
    {
        return Unit::create($request);
    }

    public function delete($id)
    {
        return Unit::destroy($id);
    }

    public function update($request, $id)
    {
        return Unit::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Unit::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
