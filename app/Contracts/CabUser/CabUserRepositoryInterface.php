<?php

namespace App\Contracts\CabUser;

interface CabUserRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);
}
