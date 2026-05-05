<?php

namespace App\Contracts\Director;

use App\Models\Director;

interface DirectorRepositoryInterface
{
    public function getAll(bool $paginate = false);

    public function find($id): Director;

    public function create($request): Director;

    public function update($request, $id): Director;

    public function updateStatus($status, $id): bool;
}
