<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdministratorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->is_admin || trim(strtolower((string) $user->role)) === 'admin')) {
            return $next($request);
        }

        abort(404);
    }
}
