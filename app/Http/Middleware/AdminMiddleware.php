<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle($request, Closure $next)
    {
        if (!Auth::check() && Auth::user()->admin_status === 1) {
            return $next($request);
    }
    
        if (!Auth::check() && Auth::user()->admin_status === 0) {
            return $next($request);
    }
    
    abort(403);
    }
}
