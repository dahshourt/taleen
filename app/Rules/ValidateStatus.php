<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateStatus implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $allowedStatusIds;

    public function __construct(array $allowedStatusIds)
    {
        $this->allowedStatusIds = $allowedStatusIds;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return in_array($value, $this->allowedStatusIds);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected status is not a valid next step in the workflow.';
    }
}
