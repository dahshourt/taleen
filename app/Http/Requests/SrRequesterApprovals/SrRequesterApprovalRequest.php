<?php

namespace App\Http\Requests\SrRequesterApprovals;

use Illuminate\Foundation\Http\FormRequest;

class SrRequesterApprovalRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'requester_id' => 'required|exists:users,id',
            'approval_level' => 'required|string|max:255',
            'active' => 'nullable|boolean',
            'approval_users' => 'nullable|array',
            'approval_users.*' => 'exists:users,id',
        ];
    }
}
