<?php

namespace App\Http\Repository\Director;

use App\Contracts\Director\DirectorRepositoryInterface;
use App\Models\Director;

class DirectorRepository implements DirectorRepositoryInterface
{
    public function getAll(bool $paginate = false)
    {
        $directors = Director::query();

        if ($paginate) {
            return $directors->paginate();
        }

        return $directors->get();
    }

    public function find($id): Director
    {
        return Director::find($id);
    }

    public function create($request): Director
    {
        return Director::create($request);
    }

    public function update($request, $id): Director
    {
        $director = Director::findOrFail($id);
        $director->update($request);

        return $director;
    }

    public function updateStatus($status, $id): bool
    {
        return Director::where('id', $id)->update(['status' => $status]);
    }
}
