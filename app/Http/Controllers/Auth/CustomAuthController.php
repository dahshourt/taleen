<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Roles\RolesRepository;
use App\Http\Repository\Users\UserRepository;
use App\Http\Requests\Auth\Api\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Log;

class CustomAuthController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    /**
     * Login
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginRequest $request)
    {
       
        $generalLoginError = 'Login error. Please contact administration.';
        $accountLockedError = 'Your account is locked due to too many failed login attempts. Please contact your administrator.';
        $maxAttempts = config('auth.max_login_attempts', 5);

        $user = User::with('user_groups', 'user_groups.group', 'defualt_group')
            ->where('user_name', $request->user_name)
            ->first();

        // User not found in local DB, try LDAP and create if successful
        if (!$user) {
            $ldapResponse = $this->checkLdapAccount($request->user_name, $request->password);

            if ($ldapResponse['status']) {
                $user = $this->createUserFromLdap($request->user_name);

                if (!$user) {
                    return redirect()->back()->withErrors(['msg' => $generalLoginError])->withInput();
                }

                $this->loginUser($user);

                return $this->getPostLoginRedirect($user);
            }

            return redirect()->back()->withErrors(['msg' => $generalLoginError])->withInput();
        }

        // Check if account is locked
        if ($user->failed_attempts >= $maxAttempts) {
            $user->active = 0;
            $user->save();
        }

        if ($user->active == 0) {
            return redirect('login')->with('failed', $accountLockedError);
        }

        // Local user login attempt
        if (isset($user->user_type) && $user->user_type == 0) {
            return $this->attemptLocalLogin($user, $request->password, $maxAttempts, $generalLoginError);
        }

        // Existing LDAP user: Verify against LDAP
        $ldapResponse = $this->checkLdapAccount($request->user_name, $request->password);

        if ($ldapResponse['status']) {
            $user->failed_attempts = 0;
            $user->save();

            $this->loginUser($user);

            return $this->getPostLoginRedirect($user);
        }

        // Failed login attempt
        $user->failed_attempts += 1;
        if ($user->failed_attempts >= $maxAttempts) {
            $user->active = 0;
        }
        $user->save();

        return redirect()->back()->withErrors(['msg' => $generalLoginError])->withInput();
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = User::where('id', $request->user()->id)->first();
            if ($user) {
                $user->device_token = null;
                $user->save();
            }
            $request->user()->tokens()->delete();

            return response()->json(['msg' => [__('messages.logout_successfully')]], 200);
        } catch (Exception $e) {
            Log::debug($e->getMessage());

            return response()->json(['msg' => [__('messages.failed_request')]], 403);
        }
    }

    /**
     * Reset Password
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $user = User::where('phone', $request->phone)->first();
            if (!$user) {
                return response()->json(['msg' => [__('messages.failed_request')]], 404);
            }
            // reset user password
            $user->password = Hash::make($request->password);
            $user->save();

            // Revoke a all user tokens...
            return response()->json(['msg' => [__('messages.reset_successfully')]], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::debug($e->getMessage());

            return response()->json(['msg' => [__('messages.failed_request')]], 403);
        }
    }

    /**
     * Update Password
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {

        try {
            DB::beginTransaction();
            $user = User::where('id', $request->user()->id)->first();
            if (Hash::check($request->old_password, $user->password)) {
                $user->password = Hash::make($request->password);
                $user->save();
            } else {
                return response()->json(['msg' => [__('messages.old_password_is_incorrect')]], 403);
            }
            DB::commit();

            return response()->json(['msg' => [__('messages.success_update')]], 200);
        } catch (Exception $e) {
            DB::rollback();
            Log::debug($e->getMessage());

            return response()->json(['msg' => [__('messages.failed_request')]], 403);
        }
    }

    public function check_active()
    {
        // dd(auth()->user(),Auth::check());
        return response()->json([
            'active' => Auth::check() ? Auth::user()->active : false,
        ]);
    }

    public function inactive_logout()
    {

        Auth::logout();

        return redirect('/login')->withErrors(['msg' => 'Login error. Please contact administration.'])->withInput();

    }

    /**
     * Check LDAP Account credentials.
     *
     * @param  string  $username
     * @param  string  $password
     * @return array
     */
    private function checkLdapAccount($username, $password)
    {
        $ldapHost = config('constants.cairo.ldap_host');
        $ldapConn = @ldap_connect($ldapHost);

        if (!$ldapConn) {
            return [
                'status' => false,
                'message' => 'There is a connection problem with ldap.',
            ];
        }

        $ldapBindDn = config('constants.cairo.ldap_binddn') . $username;
        $ldapBind = @ldap_bind($ldapConn, $ldapBindDn, $password);

        if (!$ldapBind) {
            return [
                'status' => false,
                'message' => 'Credentials Invalid.',
            ];
        }

        return [
            'status' => true,
            'message' => 'Success',
        ];
    }

    /**
     * Attempt local user login.
     *
     * @param  string  $password
     * @param  int  $maxAttempts
     * @param  string  $errorMessage
     * @return \Illuminate\Http\RedirectResponse
     */
    private function attemptLocalLogin(User $user, $password, $maxAttempts, $errorMessage)
    {
        if (Auth::attempt(['user_name' => $user->user_name, 'password' => $password])) {
            $user->failed_attempts = 0;
            $user->save();
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', Session::getId())
                ->delete();

            return $this->getPostLoginRedirect($user);
        }

        $user->failed_attempts += 1;
        if ($user->failed_attempts >= $maxAttempts) {
            $user->active = 0;
        }
        $user->save();

        return redirect('login')->with('failed', $errorMessage);
    }

    /**
     * Create a new user from LDAP information.
     *
     * @param  string  $username
     * @return User|null
     */
    private function createUserFromLdap($username)
    {
        $email = $username . '@te.eg';

        // Check if email already exists
        if (app(UserRepository::class)->CheckUniqueEmail($email)) {
            return null;
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

        return app(UserRepository::class)->create($data);
    }

    /**
     * Log the user in and clear other sessions.
     *
     * @return void
     */
    private function loginUser(User $user)
    {
        Auth::login($user);
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', Session::getId())
            ->delete();
    }


    /**
     * Determine the post-login redirect destination.
     * Users with the 'Analytics Dashboard' permission are sent to the
     * PowerBI dashboard; everyone else goes to the default home page.
     */
    private function getPostLoginRedirect(User $user)
    {
        if ($user->can('Analytics Dashboard')) {
            return redirect()->route('powerbi.dashboard');
        }

        return redirect()->intended(url('/'));
    }

}
