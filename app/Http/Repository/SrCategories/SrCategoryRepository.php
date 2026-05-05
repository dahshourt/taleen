<?php

namespace App\Http\Repository\SrCategories;

use App\Contracts\SrCategories\SrCategoryRepositoryInterface;
use App\Models\SrCategory;

class SrCategoryRepository implements SrCategoryRepositoryInterface
{
    public function getAll()
    {
        return SrCategory::all();
    }

    public function create($request)
    {
        return SrCategory::create($request);
    }

    public function delete($id)
    {
        return SrCategory::destroy($id);
    }

    public function update($request, $id)
    {
        return SrCategory::where('id', $id)->update($request);
    }

    public function paginateAll()
    {
        return SrCategory::latest()->paginate(10);
    }

    public function find($id)
    {
        return SrCategory::find($id);
    }
}
