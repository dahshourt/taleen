<?php

namespace App\Http\Controllers\BiRequestTypeHiddenFields;

use App\Http\Controllers\Controller;
use App\Models\BiRequestTypeField;
use App\Models\CustomField;
use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiRequestTypeFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requestTypes = RequestType::whereHas('BIFields')->with('BIFields')->get();
        return view('bi_request_type_fields.index', compact('requestTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $requestTypes = RequestType::all();
        $customFields = CustomField::all();
        $selectedFields = [];

        return view('bi_request_type_fields.create', compact('requestTypes', 'customFields', 'selectedFields'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_type_id' => 'required|exists:request_types,id',
            'custom_field_id' => 'required|array',
            'custom_field_id.*' => 'exists:custom_fields,id',
        ]);

        $requestType = RequestType::findOrFail($request->request_type_id);

        // Sync without detaching to append to existing, or just sync. 
        // We'll syncWithoutDetaching so if they add new ones to an existing type it merges.
        $requestType->BIFields()->syncWithoutDetaching($request->custom_field_id);

        return redirect()->route('bi-request-type-fields.index')->with('success', 'Mappings created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // $id is the request_type_id
        $requestType = RequestType::with('BIFields')->findOrFail($id);
        $requestTypes = RequestType::all();
        $customFields = CustomField::all();

        $selectedFields = $requestType->BIFields->pluck('id')->toArray();

        return view('bi_request_type_fields.edit', compact('requestType', 'requestTypes', 'customFields', 'selectedFields'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // $id is the request_type_id
        $requestType = RequestType::findOrFail($id);

        $request->validate([
            'request_type_id' => 'required|exists:request_types,id',
            'custom_field_id' => 'required|array',
            'custom_field_id.*' => 'exists:custom_fields,id',
        ]);

        if ($request->request_type_id != $id) {
            // They changed the request type for this mapping
            $requestType->BIFields()->detach();
            $newRequestType = RequestType::findOrFail($request->request_type_id);
            $newRequestType->BIFields()->sync($request->custom_field_id);
        } else {
            $requestType->BIFields()->sync($request->custom_field_id);
        }

        return redirect()->route('bi-request-type-fields.index')->with('success', 'Mappings updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // $id is the request_type_id
        $requestType = RequestType::findOrFail($id);
        $requestType->BIFields()->detach();

        return redirect()->route('bi-request-type-fields.index')->with('success', 'Mappings deleted successfully.');
    }

    /**
     * Get the mapped custom fields for the BI workflows dynamically.
     */
    public function getFields(Request $request)
    {
        $allMappedFieldIds = BiRequestTypeField::distinct()->pluck('custom_field_id');
        $allMappedFields = CustomField::whereIn('id', $allMappedFieldIds)->pluck('name');

        $selectedFields = [];
        if ($request->has('request_type_id') && $request->request_type_id) {
            $selectedFieldIds = BiRequestTypeField::where('request_type_id', $request->request_type_id)->pluck('custom_field_id');
            $selectedFields = CustomField::whereIn('id', $selectedFieldIds)->pluck('name');
        }

        return response()->json([
            'success' => true,
            'all_mapped_fields' => $allMappedFields,
            'selected_fields' => $selectedFields
        ]);
    }
}
