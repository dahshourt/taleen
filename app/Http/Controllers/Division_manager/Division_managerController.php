<?php

namespace App\Http\Controllers\Division_manager;

use App\Factories\divisionManager\DivisionManagerFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Roles\RolesRepository;
use App\Http\Repository\Users\UserRepository;
use App\Http\Requests\division_manager\division_managerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Division_managerController extends Controller
{
    private $division_manager;

    public function __construct(DivisionManagerFactory $division_manager)
    {
        $this->division_manager = $division_manager::index();
        $this->view = 'division_manager';
        $view = 'division_manager';
        $route = 'division_manager';
        $OtherRoute = 'division_manager';
        $title = 'Division Manager';
        $form_title = 'Division Manager';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index()
    {
        $this->authorize('List Division'); // permission check
        $collection = $this->division_manager->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Division'); // permission check

        return view("$this->view.create");
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(division_managerRequest $request)
    {
        $this->authorize('Create Division'); // permission check
        $this->division_manager->create($request->all());

        return redirect()->back()->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Division'); // permission check
        $row = $this->division_manager->find($id);

        return view("$this->view.edit", compact('row'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(division_managerRequest $request, $id)
    {
        $this->authorize('Edit Division'); // permission check
        $this->division_manager->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function destroy()
    {
        $this->authorize('Delete Division'); // permission check

    }

    public function updateactive(Request $request)
    {
        $this->authorize('Active Division'); // permission check
        // dd('active');
        $data = $this->division_manager->find($request->id);
        $this->division_manager->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    } // end method

    public function ActiveDirectoryCheck(Request $request)
    {
        // $mail = $request->input('email');
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:rfc,dns',
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false, 'message' => 'Please enter valid division manager mail.']);
        }

        $mail = $request->email;
        // dd($mail);

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
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);  // We need this for doing an LDAP search. // To follow referrals

            // Bind to LDAP server
            $ldapbind = ldap_bind($ldap, $ldap_binddn, $pwd);

            if ($ldapbind) {
                // Search for the email in Active Directory
                $escapedMail = ldap_escape($mail, '', LDAP_ESCAPE_FILTER);
                $search = "(mail=$escapedMail)";  // Searching for the email address
                $result = ldap_search($ldap, $ldap_rootdn, $search);

                // If search returns results, the email exists

                if (ldap_count_entries($ldap, $result) > 0) {

                    $this->syncLDAPUsers($mail);

                    // Email exists in Active Directory
                    return response()->json(['valid' => true, 'message' => 'Valid.']);
                }

                // Email does not exist in Active Directory
                return response()->json(['valid' => false, 'message' => 'Please enter valid division manager mail.']);

            }

            // LDAP bind failed
            return response()->json(['valid' => false, 'message' => 'Unable to connect to Active Directory.']);

        }

        // LDAP connection failed
        return response()->json(['valid' => false, 'message' => 'Unable to connect to LDAP server.']);

    }

    /**
     * Create a new user from LDAP information.
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
            'roles' => [$role->name],
            'default_group' => $businessGroup->id,
            'group_id' => [$businessGroup->id],
            'active' => '1',
        ];

        app(UserRepository::class)->create($data);
    }
}
