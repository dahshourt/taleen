<?php

namespace App\Contracts\Applications;

interface ApplicationRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function updateactive($active, $id);
}
