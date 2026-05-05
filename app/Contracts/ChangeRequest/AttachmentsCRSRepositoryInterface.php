<?php

namespace App\Contracts\ChangeRequest;

interface AttachmentsCRSRepositoryInterface
{
    public function add_files($data, $id, $flag);

    public function update_files($data, $id);
}
