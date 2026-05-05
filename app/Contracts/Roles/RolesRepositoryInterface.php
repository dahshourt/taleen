<?php

namespace App\Contracts\Roles;

interface RolesRepositoryInterface
{
    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function show($id);
}
