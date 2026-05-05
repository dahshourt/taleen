<?php

namespace App\Http\Repository\Categories;

use App\Contracts\Categories\CategoryRepositoryInterface;
// declare Entities
use App\Models\Category;

class CategoreyRepository implements CategoryRepositoryInterface
{
    public function getAll()
    {
        return Category::all();
    }

    public function create($request)
    {
        return Category::create($request);
    }

    public function delete($id)
    {
        return Category::destroy($id);
    }

    public function update($request, $id)
    {
        return Category::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Category::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
