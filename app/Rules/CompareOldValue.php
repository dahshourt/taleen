<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CompareOldValue implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $expectedValue;

    public function __construct($expectedValue)
    {
        $this->expectedValue = $expectedValue;
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
        return $value === $this->expectedValue;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute field cannot be changed.';
    }
}
