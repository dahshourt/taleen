<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Session;

class AuthenticateGroup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // if ( Auth::guard($guard)->check() ) {
        if (Session::get('group')) {

            return $next($request);
        }

        return redirect('/select/group');
    }
}
