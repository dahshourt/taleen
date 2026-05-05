<?php

namespace App\Http\Controllers\CustomFields\Api;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\CustomField\CustomFieldGroupTypeFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFields\Api\CustomFieldGroupTypeRequest;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\CustomFieldSelectedGroupResource;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class CustomFieldGroupTypeController extends Controller
{
    use ValidatesRequests;

    private $custom_field_group_type;

    private $changerequest;

    public function __construct(ChangeRequestFactory $changerequest, CustomFieldGroupTypeFactory $custom_field_group_type)
    {

        $this->custom_field_group_type = $custom_field_group_type::index();
        $this->changerequest = $changerequest::index();
    }

    public function CustomFieldsByFormType()
    {
        $form_type = request()->form_type;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByFormType($form_type);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function CustomFieldsByGroup()
    {
        $group_id = request()->group_id;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByGroup($group_id);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function CustomFieldsByWorkFlowType($workflow_type_id, $form_type)
    {

        $workflow_type_id = request()->workflow_type_id;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowType($workflow_type_id, $form_type);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function CustomFieldsByWorkFlowTypeAndStatus($workflow_type_id, $form_type, $status_id)
    {

        // $workflow_type_id = request()->workflow_type_id;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeAndStatus($workflow_type_id, $form_type, $status_id);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function CustomFieldsByWorkFlowTypeAndViewCrPage($workflow_type_id, $form_type)
    {

        $groupId = request()->header('group');
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeViewPage($workflow_type_id, $form_type, $groupId);

        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function AllCustomFieldsWithSelected()
    {
        $column = request()->by;
        $value = request()->value;
        $CustomFields = $this->custom_field_group_type->getAllCustomFieldsWithSelected($column, $value);
        $CustomFields = CustomFieldResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function AllCustomFieldsWithSelectedWithFormType($form_type)
    {
        $column = request()->by;
        $value = request()->value;
        $CustomFields = $this->custom_field_group_type->AllCustomFieldsWithSelectedWithFormType($column, $value, $form_type);
        $CustomFields = CustomFieldResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function AllCustomFieldsWithSelectedByformType()
    {
        $column = request()->by;
        $value = request()->value;
        $CustomFields = $this->custom_field_group_type->getAllCustomFieldsWithSelectedByformType($column, $value);
        // $CustomFields = CustomFieldResource::collection($CustomFields);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function AllCustomFieldsSelected()
    {
        $CustomFields = $this->custom_field_group_type->AllCustomFieldsSelected();
        $CustomFields = CustomFieldResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function index()
    {
        $column = request()->by;
        $value = request()->value;
        // $CustomFields = $this->custom_field_group_type->getAll();
        $CustomFields = $this->custom_field_group_type->getAllByColumnAndValue($column, $value);

        return response()->json(['data' => $CustomFields], 200);
    }

    public function Validation()
    {

        $CustomFields = $this->custom_field_group_type->getValidations();

        return response()->json(['data' => $CustomFields], 200);
    }

    public function store(CustomFieldGroupTypeRequest $request)
    {
        if (isset($request->custom_field_id)) {
            $column_value = $request->group_id ? $request->group_id : $request->wf_type_id;
            $column = $request->group_id ? 'group_id' : 'wf_type_id';
            // $this->custom_field_group_type->deleteByGroupOrType($column,$column_value);
            $this->custom_field_group_type->deleteCFs();
            foreach ($request->custom_field_id as $key => $value) {
                if (isset($value) && $value != false) {
                    $data = [
                        'form_type' => $request->form_type,
                        // 'group_id' => $request->group_id??$request->group_id,
                        // 'status_id' => $request->status_id?$request->status_id:null,
                        // 'wf_type_id' => $request->wf_type_id??$request->wf_type_id,
                        'custom_field_id' => $value,
                        // 'sort' => isset($request->sort[$key]) ? $request->sort[$key] : null,
                        // 'validation_type_id' => isset($request->validation_type_id[$key]) ? $request->validation_type_id[$key] : null,
                        // 'enable' => isset($request->enable[$key]) ? $request->enable[$key] : null,
                        'active' => $request->active,
                    ];

                    if ($request->status_id) {
                        $data['status_id'] = $request->status_id;
                    }
                    if ($request->group_id) {
                        $data['group_id'] = $request->group_id;
                    }
                    if ($request->wf_type_id) {
                        $data['wf_type_id'] = $request->wf_type_id;
                    }
                    if (isset($request->sort[$key])) {
                        $data['sort'] = $request->sort[$key];
                    }
                    if (isset($request->validation_type_id[$key])) {
                        $data['validation_type_id'] = $request->validation_type_id[$key];
                    }
                    if (isset($request->enable[$key])) {
                        $data['enable'] = $request->enable[$key];
                    }

                    $this->custom_field_group_type->create($data);
                }
            }

            return response()->json([
                'message' => 'Created Successfully',
            ]);
        }

        return response()->json([
            'message' => 'Custom Fields required',
        ], 422);

    }

    public function update(CustomFieldGroupTypeRequest $request, $id)
    {
        $CustomField = $this->custom_field_group_type->find($id);
        if (! $CustomField) {
            return response()->json([
                'message' => 'Group Not Exists',
            ], 422);
        }
        $this->CustomField->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $CustomField = $this->custom_field_group_type->find($id);

        return response()->json(['data' => $CustomField], 200);
    }

    public function CustomFieldsForRealeases($form_type)
    {

        $CustomFields = $this->custom_field_group_type->CustomFieldsForReleases($form_type);
        $CustomFields = CustomFieldSelectedGroupResource::collection($CustomFields);

        return response()->json(['data' => $CustomFields], 200);
    }
}
