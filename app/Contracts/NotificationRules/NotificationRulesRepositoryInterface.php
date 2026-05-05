<?php

namespace App\Contracts\NotificationRules;

interface NotificationRulesRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function getWithRecipients($id);
}
