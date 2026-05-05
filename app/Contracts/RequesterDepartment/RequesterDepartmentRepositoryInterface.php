<?php

namespace App\Contracts\RequesterDepartment;

interface RequesterDepartmentRepositoryInterface
{
    public function getAll();
    public function create($request);
    public function delete($id);
    public function update($request, $id);
    public function paginateAll();
    public function find($id);
    public function updateactive($active, $id);
}
