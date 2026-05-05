<?php

namespace App\Contracts\Prerequisites;

interface PrerequisitesRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $model);

    public function delete($id);
}
