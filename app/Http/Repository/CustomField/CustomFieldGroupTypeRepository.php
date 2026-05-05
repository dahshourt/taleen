<?php

namespace App\Http\Repository\CustomField;

use App\Contracts\CustomField\CustomFieldGroupTypeRepositoryInterface;
// declare Entities
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\ValidationType;

class CustomFieldGroupTypeRepository implements CustomFieldGroupTypeRepositoryInterface
{
    public function getValidations()
    {
        return ValidationType::all();
    }

    public function getAll()
    {
        return CustomFieldGroup::all();
    }

    public function getAllByColumnAndValue($column, $value)
    {
        return CustomFieldGroup::where($column, $value)->get();
    }

    public function getAllCustomFieldsWithSelected($column, $value)
    {
        $result = CustomField::with([
            'custom_field_group' => function ($q) use ($column, $value) {
                $q->where($column, $value);
            }
        ])->get();

        return $result;
        // return CustomField::with('custom_field_group')->get();
    }

    public function AllCustomFieldsWithSelectedWithFormType($column, $value, $form_type)
    {
        // \DB::enableQueryLog();
        $result = CustomField::with([
            'custom_field_group' => function ($q) use ($column, $value, $form_type) {
                $q->where($column, $value)->where('form_type', $form_type);
            }
        ])->get();
        // $queries = \DB::getQueryLog();
        // $lastQuery = end($queries);

        // // Print the last executed query
        // dd($lastQuery);

        return $result;
        // return CustomField::with('custom_field_group')->get();
    }

    public function create($request)
    {
        // \DB::enableQueryLog();
        //        echo"<pre>";
        // print_r($request);
        // echo "</pre>"; die;
        $customFieldGroup = CustomFieldGroup::create($request);

        // Get the last executed query
        // $queries = \DB::getQueryLog();
        // $lastQuery = end($queries);

        // Print the last executed query
        // dd($lastQuery);

        // Log the last executed query

        //  die("dd6gg006");
        return $customFieldGroup;
        // die;
    }

    public function delete($id)
    {
        return CustomFieldGroup::destroy($id);
    }

    public function update($request, $id)
    {
        return CustomFieldGroup::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return CustomFieldGroup::find($id);
    }

    public function deleteByGroupOrType($column, $value)
    {
        return CustomFieldGroup::where($column, $value)->delete();
    }

    public function deleteCFs()
    {
        $CFs = new CustomFieldGroup;
        $CFs = $CFs->where('form_type', request()->form_type);
        if (request()->group_id) {
            $CFs = $CFs->where('group_id', request()->group_id);
        }
        if (request()->wf_type_id) {
            $CFs = $CFs->where('wf_type_id', request()->wf_type_id);
        }
        // Check if status_id exists, otherwise set it to null
        $CFs = $CFs->where('status_id', request()->status_id ?? null);

        // Print the SQL query
        // dd($CFs->toSql(), $CFs->getBindings());
        return $CFs->delete();
    }

    public function CustomFieldsByGroup($group_id)
    {
        $result = CustomField::whereHas('custom_field_group', function ($q) use ($group_id) {
            $q->where('group_id', $group_id);
        })->with('custom_field_group')->get();

        return $result;
    }

    public function CustomFieldsByWorkFlowType($workflow_type_id, $form_type)
    {

        $result = CustomFieldGroup::with('CustomField')->where('wf_type_id', $workflow_type_id)->where('form_type', $form_type)->orderBy('sort')->get()->unique('CustomField.id');

        return $result;

    }

    public function CustomFieldsByWorkFlowTypeAndStatus($workflow_type_id, $form_type, $status_id)
    {
        // First, get custom fields specifically for this status
        $specificStatusFields = CustomFieldGroup::with('CustomField')
            ->where('wf_type_id', $workflow_type_id)
            ->where('form_type', $form_type)
            ->where('status_id', $status_id)
            ->orderBy('sort')
            ->get();

        // Then, get custom fields with NULL status_id that don't exist in specific status
        $specificFieldIds = $specificStatusFields->pluck('custom_field_id')->toArray();
        
        $nullStatusFields = CustomFieldGroup::with('CustomField')
            ->where('wf_type_id', $workflow_type_id)
            ->where('form_type', $form_type)
            ->whereNull('status_id')
            ->whereNotIn('custom_field_id', $specificFieldIds)
            ->orderBy('sort')
            ->get();

        // Combine and return results
        $result = $specificStatusFields->concat($nullStatusFields);
        
        return $result;
    }

    public function CustomFieldsByWorkFlowTypeViewPage($workflow_type_id, $form_type, $groupId)
    {

        $result = CustomFieldGroup::with('CustomField')->where('wf_type_id', $workflow_type_id)->where('form_type', $form_type)
            ->where(function ($query) use ($groupId) {
                $query->where('group_id', $groupId)->orWhereNULL('group_id');
            })->orderBy('sort')->get();

        // $result = CustomField::whereHas('custom_field_by_workflow', function($q) use($workflow_type_id, $form_type, $groupId){
        //     $q->where('wf_type_id',$workflow_type_id)->where('form_type',$form_type)->where(function($query) use($groupId) {
        //         $query->where('group_id', $groupId)->orWhereNULL('group_id');
        //     });
        // })->with('custom_field_by_workflow')->get();

        return $result;
    }

    public function AllCustomFieldsSelected()
    {

        $result = CustomField::with([
            'custom_field_group' => function ($q) {
                $q->where('form_type', request()->form_type);
                $q->where('wf_type_id', request()->wf_type_id);
                if (request()->group_id) {
                    $q->where('group_id', request()->group_id);
                } else {
                    $q->WhereNULL('group_id');
                }
                if (request()->status_id) {
                    $q->where('status_id', request()->status_id);
                } else {
                    $q->WhereNULL('status_id');
                }
            }
        ])->get();

        return $result;
        // return CustomField::with('custom_field_group')->get();
    }

    public function CustomFieldsByFormType($form_type)
    {
        $result = CustomField::whereHas('custom_field_group', function ($q) use ($form_type) {
            $q->where('form_type', $form_type);
        })->with('custom_field_group')->get();

        return $result;
    }

    public function getAllCustomFieldsWithSelectedByformType($column, $value)
    {
        $result = CustomFieldGroup::with('CustomField')->where($column, $value)->orderBy('sort')->get();

        // $result = CustomField::whereHas('custom_field_group', function($q) use($column,$value){
        //     $q->where($column,$value);
        // })->with('custom_field_group')->get();
        return $result;

    }

    public function CustomFieldsForReleases($form_type)
    {

        /* $result = CustomField::whereHas('custom_field_by_workflow', function($q) use($form_type){
             $q->where('form_type',$form_type);
         })->get();
         return $result; */
        $result = CustomFieldGroup::with('CustomField')->where('form_type', $form_type)->orderBy('sort')->get();

        return $result;
    }
}
