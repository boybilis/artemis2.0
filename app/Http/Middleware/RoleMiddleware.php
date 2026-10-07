<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = trim(strtolower((string) $user?->role));

        if ($user && ($user->is_admin || in_array($role, $roles, true))) {
            return $next($request);
        }

        abort(404);
    }
}
