<?php

namespace App\Http\Requests\SrCategories;

use Illuminate\Foundation\Http\FormRequest;

class SrCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->isMethod('POST')) {
            return $this->createRules();
        }

        return $this->updateRules();
    }

    /**
     * Get the create validation rules that apply to the request.
     *
     * @return array
     */
    public function createRules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:sr_categories'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the update validation rules that apply to the request.
     *
     * @return array
     */
    public function updateRules()
    {
        // Route parameter is 'sr_category' for 'sr-categories' resource
        $id = $this->route('sr_category'); 
        
        return [
            'name' => ['required', 'string', 'max:255', 'unique:sr_categories,name,' . $id],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
