<?php

namespace App\Contracts\CustomField;

interface CustomFieldRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);
}
