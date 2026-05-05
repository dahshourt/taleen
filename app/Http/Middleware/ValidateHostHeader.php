<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateHostHeader
{
    public function handle(Request $request, Closure $next)
    {
        // $allowedHosts = [
        //     '10.19.44.26', // your server IP
        // ];

        // if (!in_array($request->getHost(), $allowedHosts)) {
        //     abort(400, 'Invalid Host Header');
        // }

        return $next($request);
    }
}
