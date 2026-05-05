<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DivisionManagerExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $name = config('constants.active-directory.name');
        $pwd = config('constants.active-directory.pwd');
        $ldap_host = config('constants.active-directory.ldap_host');
        $ldap_binddn = config('constants.active-directory.ldap_binddn') . $name;
        $ldap_rootdn = config('constants.active-directory.ldap_rootdn');

        $ldap = ldap_connect($ldap_host);
        if (!$ldap) {
            return false;
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        if (!ldap_bind($ldap, $ldap_binddn, $pwd)) {
            return false;
        }

        $escapedMail = ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        $result = ldap_search($ldap, $ldap_rootdn, "(mail=$escapedMail)");

        return ldap_count_entries($ldap, $result) > 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Please enter a valid division manager mail.';

    }
}
