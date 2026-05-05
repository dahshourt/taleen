<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use App\Rules\MdsApproversExists;
use Illuminate\Http\Request;

class MdsApproversValidationController extends Controller
{
    /**
     * Validate MDS Approvers username
     */
    public function validateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string'
        ]);

        $validator = new MdsApproversExists();
        $isValid = $validator->passes('email', $request->email);

        if ($isValid) {
            // Username exists in Active Directory
            return response()->json(['valid' => true, 'message' => 'Valid.']);
        } else {
            // Username does not exist in Active Directory
            return response()->json(['valid' => false, 'message' => 'Please enter valid MDS approver username.']);
        }
    }
}
