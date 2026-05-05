<?php

namespace App\Http\Controllers\ChangeRequest\Api;

use App\Http\Controllers\Controller;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\ChangeRequestCustomField;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class EmailApprovalController extends Controller
{
    /**
     * 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function UpdateEmailApprovel(Request $request)
    {
        
        $authUsername = $request->header('username');
        $authPassword = $request->header('password');
        
        
        $configUsername = Config::get('api.rpa.username');
        $configPassword = Config::get('api.rpa.password');
        
        
        if ($authUsername !== $configUsername || $authPassword !== $configPassword) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
            ], 401);
        }
        
        
        $validator = Validator::make($request->all(), [
			'cr_id'      => 'required|integer',
			'division_email' => 'required|email',
			'action'     => 'required|string|in:approved,reject,rejected',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status'  => false,
				'message' => 'Validation errors',
				'errors'  => $validator->errors()
			], 422);
		}
        
        
        //$cr = Change_request::find($request->cr_id);
        $cr = Change_request::where('cr_no',$request->cr_id)->first();
		
        $crId = $request->cr_id;
        $action = $request->action;
        $fromEmail = $request->division_email;

        if (!$cr) {
            return response()->json([
                'status' => 'error',
                'message' => 'CR not exist'
            ], 404);
        }
		
		if (strtolower($fromEmail) !== strtolower($cr->division_manager)) {
            return response()->json([
                'status' => 'error',
                'message' => "Unauthorized attempt for CR #{$crId} from {$fromEmail}"
            ], 405);
        }
		
		$currentStatus = Change_request_statuse::where('cr_id', $cr->id)->where('active', '1')->value('new_status_id');
		
        if($currentStatus != '22'){
			return response()->json([
                'status' => 'error',
                'message' => "CR #{$crId} is not in bussiness approval status whilst processing {$action} from {$fromEmail}"
            ], 405);

        }
        if (in_array($action, ['approved', 'approve'])) {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();
        } elseif (in_array($action, ['rejected', 'reject'])) {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '1')->pluck('id')->first();
        }
        /*
		if ($cr->workflow_type_id == 3) {
            $newStatus = $action === 'approved' ? 36 : 35;
        } elseif ($cr->workflow_type_id == 5) {
            $newStatus = $action === 'approved' ? 188 : 184;
        } else {
			return response()->json([
                'status' => 'error',
                'message' => "Unsupported workflow for CR #{$crId}"
            ], 405);
            
        }
		*/		
		$repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

        $user = \App\Models\User::where('email', $fromEmail)->first();
        $userId = $user ? $user->id : null;

        $req = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatus,
            'new_status_id' => $workflow_type_id,
            //propagate sender email for repo user resolution logic
            'assign_to'     => null,
            'user_id'       => $userId,
        ]);

        
        try {
			
            $repo->UpateChangeRequestStatus($cr->id, $req);
			return response()->json([
				'status' => 'success',
				'message' => "CR #{$crId} {$action} successfully by {$fromEmail}"
			], 200);
        } catch (\Throwable $e) {
			return response()->json([
				'status' => 'failed',
				'message' => "Failed to {$action} CR #{$crId}". $e
			], 200);
        }
       
        
    }
	
	
	public function ApproveMail(Request $request)
	{
		
		$authUsername = "RPA_user";
        $authPassword = "RPA_password";
        
        
        $configUsername = Config::get('api.rpa.username');
        $configPassword = Config::get('api.rpa.password');
        
        
        if ($authUsername !== $configUsername || $authPassword !== $configPassword) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
			'cr_id'      => 'required|integer',
			'division_email' => 'required|email',
			'action'     => 'required|string|in:approved,reject,rejected',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status'  => false,
				'message' => 'Validation errors',
				'errors'  => $validator->errors()
			], 422);
		}
        
        
        //$cr = Change_request::find($request->cr_id);
        $cr = Change_request::where('cr_no',$request->cr_id)->first();
		
        $crId = $request->cr_id;
        $action = $request->action;
        $fromEmail = $request->division_email;

        if (!$cr) {
            return response()->json([
                'status' => 'error',
                'message' => 'CR not exist'
            ], 404);
        }
		
		if (strtolower($fromEmail) !== strtolower($cr->division_manager)) {
            return response()->json([
                'status' => 'error',
                'message' => "Unauthorized attempt for CR #{$crId} from {$fromEmail}"
            ], 405);
        }
		
		$currentStatus = Change_request_statuse::where('cr_id', $cr->id)->where('active', '1')->value('new_status_id');
		
        if($currentStatus != '22'){
			return response()->json([
                'status' => 'error',
                'message' => "CR #{$crId} is not in bussiness approval status whilst processing {$action} from {$fromEmail}"
            ], 405);

        }
		if ($cr->workflow_type_id == 3) {
            $newStatus = $action === 'approved' ? 36 : 35;
        } elseif ($cr->workflow_type_id == 5) {
            $newStatus = $action === 'approved' ? 188 : 184;
        } else {
			return response()->json([
                'status' => 'error',
                'message' => "Unsupported workflow for CR #{$crId}"
            ], 405);
            
        }
		
		$repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

        $req = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatus,
            'new_status_id' => $newStatus,
            //propagate sender email for repo user resolution logic
            'assign_to'     => null,
        ]);

        try {
			
            $repo->UpateChangeRequestStatus($cr->id, $req);
			return response()->json([
				'status' => 'success',
				'message' => "CR #{$crId} {$action} successfully by {$fromEmail}"
			], 200);
        } catch (\Throwable $e) {
			return response()->json([
				'status' => 'failed',
				'message' => "Failed to {$action} CR #{$crId}". $e
			], 200);
        }
	}
}
