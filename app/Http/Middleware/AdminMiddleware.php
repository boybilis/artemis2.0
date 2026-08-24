<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && (Auth::user()->is_admin || in_array(trim(strtolower(Auth::user()->role)), ['admin', 'instructor']))) {
            return $next($request);
        }

        abort(404);
    }
}
