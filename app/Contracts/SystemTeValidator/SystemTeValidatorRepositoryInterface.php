<?php

namespace App\Contracts\SystemTeValidator;

interface SystemTeValidatorRepositoryInterface
{
    public function getAll();
    public function create($request);
    public function delete($id);
    public function update($request, $id);
    public function find($id);
    public function updateactive($active, $id);
    public function getUsersBySystem($system_id);
}
