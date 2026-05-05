<?php

namespace App\Contracts\SrCategories;

interface SrCategoryRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function paginateAll();
}
