<?php

namespace App\Http\Controllers\Auth\Api;

use Adldap\Laravel\Facades\Adldap;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Api\LoginRequest;
use App\Models\User;
use App\Models\UserGroups;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Log;

class LoginController extends Controller
{
    /**
     * Login
     *
     * @param  [string] phone
     * @param  [string] password
     * @return [object] user data
     *
     * @throws \SMartins\PassportMultiauth\Exceptions\MissingConfigException
     */
    public function login(LoginRequest $request)
    {
        $request->validate([
            'user_name' => 'required',
            'password' => 'required',
        ]);

        // Check if user is authenticated or no
        $user = User::with('user_groups', 'user_groups.group', 'defualt_group')->where('user_name', $request->user_name)->first();

        if (isset($user->user_type) && $user->user_type == 0) {

            if (is_null($user) || ! Hash::check($request->password, $user->password)) {

                return response()->json(['message' => ['password' => ['Incorrect Password']]], 422);
            }

            if (! $user->active) {
                return response()->json(['message' => ['active' => ['Your Account is inactive. Please Contact your adminstrator']]], 422);
            }

            // generate access token for user
            $accessToken = $user->createToken('managment-system')->plainTextToken;
            if (! $user->last_login) {
                $user->last_login = \Carbon\Carbon::now();
            }
            $data['user'] = $user;
            $data['access_token'] = $accessToken;

            $userData = User::where('user_name', $request->user_name)->first();
            $userData->last_login = \Carbon\Carbon::now();
            $userData->save();

            return response()->json(['data' => $data], 200);
        }

        $credentials = $request->only('user_name', 'password');

        $user_attemp = Auth::attempt($credentials);

        $ldapUser = Adldap::search()->users()->find($request->user_name);

        $username = $ldapUser->getUserPrincipalName();
        $user = Adldap::auth()->attempt($username, $request->password);

        if ($user) {

            $user = User::with('user_groups', 'user_groups.group', 'defualt_group')->where('user_name', $request->user_name)->first();
            if (! $user) {
                $user = new User;
                $user->flag = '0';
                $user->user_type = '1';
            }

            $user->user_name = $request->user_name;
            $user->email = $request->user_name . '@te.eg';
            $user->password = $request->password;
            $user->name = $ldapUser->getCommonName();
            // $user->last_name = $ldapUser->getCommonName();
            // $user->user_type = 0;

            $user->active = '1';
            $user->default_group = '19';
            $user->save();
            $user_groups = new UserGroups();
            $user_groups->user_id = $user->id;
            $user_groups->group_id = '19';
            $user_groups->save();
            $user = User::with('user_groups', 'user_groups.group', 'defualt_group')->where('user_name', $request->user_name)->first();
            Auth::login($user);

            $accessToken = $user->createToken('managment-system')->plainTextToken;
            if (! $user->last_login) {
                $user->last_login = \Carbon\Carbon::now();
            }
            $data['user'] = $user;
            $data['access_token'] = $accessToken;

            return response()->json(['data' => $data], 200);
        }

        Config::set('ldap_auth.connection', 'cairo');
        $ldapUser = Adldap::search()->users()->find($request->user_name);
        $username = $ldapUser->getUserPrincipalName();
        $user = Adldap::auth()->attempt($username, $request->password);
        if ($user) {

            $user = User::with('user_groups', 'user_groups.group', 'defualt_group')->where('user_name', $request->user_name)->first();
            if (! $user) {
                $user = new User;
                $user->flag = '0';
            }

            $user->user_name = $request->user_name;
            $user->email = $request->user_name . '@te.eg';
            $user->password = $request->password;
            $user->first_name = $ldapUser->getCommonName();
            $user->last_name = $ldapUser->getCommonName();

            // $user->objectguid = $ldapUser->getConvertedGuid();
            $user->active = '1';
            $user->default_group = '19';
            $user->save();
            $user_groups = new UserGroups();
            $user_groups->user_id = $user->id;
            $user_groups->group_id = '19';
            $user_groups->save();
            $user = User::with('user_groups', 'user_groups.group', 'defualt_group')->where('user_name', $request->user_name)->first();
            Auth::login($user);

            $accessToken = $user->createToken('managment-system')->plainTextToken;
            if (! $user->last_login) {
                $user->last_login = \Carbon\Carbon::now();
            }
            $data['user'] = $user;
            $data['access_token'] = $accessToken;

            return response()->json(['data' => $data], 200);
        }

        return response()->json(['message' => ['active' => ['Your data   is   incorrect  in your active directly']]], 422);

    }

    /**
     * Logout user (Revoke the token)
     *
     * @param  [string] token
     * @return [array] msg
     */
    public function logout(Request $request)
    {
        try {
            $user = User::where('id', $request->user()->id)->first();
            $user->device_token = null;
            $user->save();
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
     * @param  [string] password
     * @param  [string] password confiramtion
     * @return [array] msg
     *
     * @throws \SMartins\PassportMultiauth\Exceptions\MissingConfigException
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $user = User::where('phone', $request->phone)->first();
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
     * @param  [string] old_password
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [array] msg
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
}
