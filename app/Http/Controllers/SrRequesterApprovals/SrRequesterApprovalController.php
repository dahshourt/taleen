<?php

namespace App\Http\Controllers\SrRequesterApprovals;

use App\Factories\SrRequesterApprovals\SrRequesterApprovalFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SrRequesterApprovals\SrRequesterApprovalRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use App\Models\User;

class SrRequesterApprovalController extends Controller
{
    use ValidatesRequests;

    private $repository;
    private $view = 'sr_requester_approvals';

    public function __construct(SrRequesterApprovalFactory $factory)
    {
        $this->repository = $factory::index();

        $title = 'SR Requester Approvals';
        $view = 'sr_requester_approvals';
        $route = 'sr-requester-approvals';
        view()->share(compact('view', 'title', 'route'));
    }

    public function index()
    {
        $this->authorize('List SR Requester Approvals');
        $collection = $this->repository->paginateAll();
        return view("{$this->view}.index", compact('collection'));
    }

    public function create()
    {
        $this->authorize('Create SR Requester Approvals');
        $title = 'Create SR Requester Approval';
        $users = User::where('active', '1')->get();
        return view("{$this->view}.create", compact('title', 'users'));
    }

    public function store(SrRequesterApprovalRequest $request)
    {
        $this->authorize('Create SR Requester Approvals');
        $this->repository->create($request->validated());
        return redirect()->route('sr-requester-approvals.index')->with('success', 'Approval configuration created successfully.');
    }

    public function edit(int $id)
    {
        $this->authorize('Edit SR Requester Approvals');
        $row = $this->repository->find($id);
        $title = 'Edit SR Requester Approval';
        $users = User::where('active', '1')->get();
        return view("{$this->view}.edit", compact('row', 'title', 'users'));
    }

    public function update(SrRequesterApprovalRequest $request, int $id)
    {
        $this->authorize('Edit SR Requester Approvals');
        $this->repository->update($request->validated(), $id);
        return redirect()->route('sr-requester-approvals.index')->with('success', 'Approval configuration updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->authorize('Delete SR Requester Approvals');
        $this->repository->delete($id);
        return redirect()->route('sr-requester-approvals.index')->with('success', 'Approval configuration deleted successfully.');
    }

    public function updateStatus(Request $request)
    {
        $this->authorize('Edit SR Requester Approvals');
        try {
            $this->repository->find($request->id)->update(['active' => $request->status]);
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating status.'
            ], 500);
        }
    }

    public function getByEmail(Request $request)
    {
        $email = $request->input('email');
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Email is required.']);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.']);
        }

        $approval = $this->repository->model()->where('requester_id', $user->id)
            ->where('active', 1)
            ->with('approvalUsers')
            ->first();

        if (!$approval) {
            return response()->json(['success' => false, 'message' => 'No active approval configuration found for this requester.']);
        }

        $approvalUsersNames = $approval->approvalUsers->pluck('user_name')->implode(', ');

        return response()->json([
            'success' => true,
            'approval_level' => $approval->approval_level,
            'approved_by' => $approvalUsersNames,
        ]);
    }
}
