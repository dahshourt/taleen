<?php

namespace App\Http\Repository\Priorities;

use App\Contracts\Priorities\PriorityRepositoryInterface;
// declare Entities
use App\Models\Priority;

class priorityRepository implements PriorityRepositoryInterface
{
    public function getAll()
    {
        return Priority::all();
    }

    public function create($request)
    {
        return Priority::create($request);
    }

    public function delete($id)
    {
        return Priority::destroy($id);
    }

    public function update($request, $id)
    {
        return Priority::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Priority::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
