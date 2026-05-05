<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Session;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

        $this->middleware('guest')->except('logout', 'inactive_logout');
    }

    public function username()
    {
        return 'user_name';
    }

    public function check_active()
    {

        return response()->json([
            'active' => Auth::check() ? Auth::user()->active : false,
        ]);
    }

    public function inactive_logout()
    {

        Auth::logout();

        return redirect('/login')->withErrors(['msg' => 'Login error. Please contact administration.'])->withInput();

    }

    public function logout()
    {
        // Forget the specific session data
        Session::forget('default_group');
        Session::forget('default_group_name');

        // Perform the normal logout process
        auth()->logout();

        // Optionally clear the entire session
        // Session::flush(); // If you want to remove all session data

        // Redirect to login or any other page
        return redirect('/login');
    }

    protected function credentials(Request $request)
    {
        return [
            'user_name' => $request->user_name,
            'password' => $request->password,
            'active' => '1',
        ];
    }

    protected function redirectTo()
    {
        return '/';
    }

    protected function redirectPath()
    {
        $path = '/';

        return $path;
    }

    protected function authenticated(Request $request, $user)
    {
        return redirect('/');
    }
}
