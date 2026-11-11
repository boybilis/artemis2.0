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
            $allowedContentDeletion = $request->isMethod('DELETE') && in_array($name, [
                'admin.content.subjects.destroy',
                'admin.content.topics.destroy',
                'admin.content.subtopics.destroy',
            ], true);
            $restricted = (!$allowedContentDeletion && ($request->isMethod('DELETE') || str_starts_with($action, 'destroy')))
                || str_contains($name, 'audit-logs')
                || $name === 'admin.users.role'
                || in_array($action, ['deleteAllQuestions', 'resetCourseAssessmentAttempt', 'unenrollCourseStudent'], true);
            abort_if($restricted, 403, 'Asst. Admin cannot view audit trails, change roles, or permanently delete records.');
        }
        return $next($request);
    }
}
