<?php

namespace App\Contracts\Groups;

interface GroupRepositoryInterface
{
    public function getAll();

    public function getAllWithFilter($parent_id);

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function updateactive($active, $id);
}
