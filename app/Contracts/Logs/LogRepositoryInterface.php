<?php

namespace App\Contracts\Logs;

interface LogRepositoryInterface
{
    public function getAll();

    public function get_by_cr_id($id);

    public function create($request);
}
