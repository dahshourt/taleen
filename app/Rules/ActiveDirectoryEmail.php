<?php

namespace App\Rules;

use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Roles\RolesRepository;
use App\Http\Repository\Users\UserRepository;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

class ActiveDirectoryEmail implements Rule
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
        if (empty($value)) {
            return true; // Use 'required' rule if it must not be empty
        }

        // connection details
        $name = config('constants.active-directory.name');
        $pwd = config('constants.active-directory.pwd');
        $ldap_host = config('constants.active-directory.ldap_host');
        $ldap_binddn = config('constants.active-directory.ldap_binddn') . $name;
        $ldap_rootdn = config('constants.active-directory.ldap_rootdn');

        // Establish LDAP connection
        $ldap = ldap_connect($ldap_host);

        if ($ldap) {
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

            // Bind to LDAP server
            $ldapbind = @ldap_bind($ldap, $ldap_binddn, $pwd);

            if ($ldapbind) {
                // Search for the email in Active Directory
                $escapedMail = ldap_escape($value, '', LDAP_ESCAPE_FILTER);
                $search = "(mail=$escapedMail)";
                $result = ldap_search($ldap, $ldap_rootdn, $search);

                if (ldap_count_entries($ldap, $result) > 0) {
                    $this->syncLDAPUsers($value);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is not a valid email on the Active Directory.';
    }

    /**
     * Create a new user from LDAP information if they don't exist.
     */
    private function syncLDAPUsers(string $email): void
    {
        // Check if email already exists
        if (app(UserRepository::class)->CheckUniqueEmail($email)) {
            return;
        }

        $username = $email;

        if (Str::endsWith($email, '@te.eg')) {
            $username = Str::remove('@te.eg', $email);
        }

        $role = app(RolesRepository::class)->findByName('Viewer');
        $businessGroup = app(GroupRepository::class)->findByName('Business Team');

        $data = [
            'user_type' => 1,
            'name' => $username,
            'user_name' => $username,
            'email' => $email,
            'roles' => $role ? [$role->name] : [],
            'default_group' => $businessGroup ? $businessGroup->id : null,
            'group_id' => $businessGroup ? [$businessGroup->id] : [],
            'active' => '1',
        ];

        app(UserRepository::class)->create($data);
    }
}
