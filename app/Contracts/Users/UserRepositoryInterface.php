<?php

namespace App\Contracts\Users;

interface UserRepositoryInterface
{
    public function getAll();

    public function paginateAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function updateactive($active, $id);

    public function get_users_te_validators($system_id = null);
}
