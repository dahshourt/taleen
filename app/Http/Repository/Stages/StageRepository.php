<?php

namespace App\Http\Repository\Stages;

use App\Contracts\Stages\StageRepositoryInterface;
// declare Entities
use App\Models\Stage;

class StageRepository implements StageRepositoryInterface
{
    public function getAll()
    {
        return Stage::all();
    }

    public function create($request)
    {
        return Stage::create($request);
    }

    public function delete($id)
    {
        return Stage::destroy($id);
    }

    public function update($request, $id)
    {
        return Stage::where('id', $id)->update($request);
    }

    public function paginateAll()
    {
        return Stage::latest()->paginate(10);
    }

    public function find($id)
    {
        return Stage::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
