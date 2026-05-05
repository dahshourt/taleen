<?php

namespace App\Contracts\Statuses;

interface StatusRepositoryInterface
{
    public static function getAll();

    public function paginateAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function updateactive($active, $id);
}
