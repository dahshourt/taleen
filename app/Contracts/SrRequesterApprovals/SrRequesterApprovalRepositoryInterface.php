<?php

namespace App\Contracts\SrRequesterApprovals;

interface SrRequesterApprovalRepositoryInterface
{
    public function getAll();
    public function paginateAll();
    public function find($id);
    public function create($request);
    public function update($request, $id);
    public function delete($id);
}
