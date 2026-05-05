<?php

namespace App\Contracts\Units;

use App\Models\Unit;

interface UnitsRepositoryInterface
{
    public function getAll(bool $paginate = false);

    public function find($id): Unit;

    public function create($request): Unit;

    public function update($request, $id): Unit;

    public function updateStatus($status, $id): bool;
}
