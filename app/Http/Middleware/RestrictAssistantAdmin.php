<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictAssistantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAssistantAdmin()) {
            $route = $request->route();
            $name = (string) $route?->getName();
            $action = (string) $route?->getActionMethod();
            $restricted = $request->isMethod('DELETE')
                || str_contains($name, 'audit-logs')
                || $name === 'admin.users.role'
                || str_starts_with($action, 'destroy')
                || in_array($action, ['deleteAllQuestions', 'resetCourseAssessmentAttempt', 'unenrollCourseStudent'], true);
            abort_if($restricted, 403, 'Asst. Admin cannot view audit trails, change roles, or permanently delete records.');
        }
        return $next($request);
    }
}
