<?php

namespace App\Http\Controllers\ChangeRequest\Api;

use App\Http\Controllers\Controller;
use App\Models\Change_request;
use App\Models\ChangeRequestCustomField;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class TechnicalFeedbackController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function addTechnicalFeedback(Request $request)
    {

        $authUsername = $request->header('username');
        $authPassword = $request->header('password');

        $configUsername = Config::get('api.auth.username');
        $configPassword = Config::get('api.auth.password');

        if ($authUsername !== $configUsername || $authPassword !== $configPassword) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed',
            ], 401);
        }

        $request->validate([
            'cr_id' => 'required|integer',
            'technical_feedback' => 'required|string',
        ]);

        // $cr = Change_request::find($request->cr_id);
        $cr = Change_request::where('cr_no', $request->cr_id)->first();

        if (! $cr) {
            return response()->json([
                'status' => 'error',
                'message' => 'CR not exist',
            ], 404);
        }

        $customFieldDefinition = CustomField::where('name', 'technical_feedback')->first();

        $customField = new ChangeRequestCustomField();
        $customField->cr_id = $cr->id;
        $customField->custom_field_id = $customFieldDefinition->id;
        $customField->custom_field_name = 'technical_feedback';
        $customField->custom_field_value = $request->technical_feedback;
        $customField->user_id = Config::get('api.user_id');

        // Save Record
        $customField->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Technical feedback added successfully',
        ], 200);
    }
}
