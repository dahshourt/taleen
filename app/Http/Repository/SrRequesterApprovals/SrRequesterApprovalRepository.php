<?php

namespace App\Http\Repository\SrRequesterApprovals;

use App\Contracts\SrRequesterApprovals\SrRequesterApprovalRepositoryInterface;
use App\Models\SrRequesterApproval;

class SrRequesterApprovalRepository implements SrRequesterApprovalRepositoryInterface
{
    public function getAll()
    {
        return SrRequesterApproval::with(['requester', 'approvalUsers'])->get();
    }

    public function paginateAll($limit = 10)
    {
        return SrRequesterApproval::with(['requester', 'approvalUsers'])->orderBy('id', 'desc')->paginate($limit);
    }

    public function find($id)
    {
        return SrRequesterApproval::with(['requester', 'approvalUsers'])->findOrFail($id);
    }

    public function create($request)
    {
        $approvalUsers = $request['approval_users'] ?? [];
        unset($request['approval_users']);

        $srRequesterApproval = SrRequesterApproval::create($request);
        $srRequesterApproval->approvalUsers()->sync($approvalUsers);

        return $srRequesterApproval;
    }

    public function update($request, $id)
    {
        $srRequesterApproval = $this->find($id);

        $approvalUsers = $request['approval_users'] ?? [];
        unset($request['approval_users']);

        $srRequesterApproval->update($request);
        $srRequesterApproval->approvalUsers()->sync($approvalUsers);

        return $srRequesterApproval;
    }

    public function delete($id)
    {
        return SrRequesterApproval::destroy($id);
    }
}
