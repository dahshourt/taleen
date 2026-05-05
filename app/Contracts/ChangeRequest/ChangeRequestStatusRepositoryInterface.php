<?php

namespace App\Contracts\ChangeRequest;

interface ChangeRequestStatusRepositoryInterface
{
    public function create($request);

    public function update_status($request, $id, $user_id);
}
