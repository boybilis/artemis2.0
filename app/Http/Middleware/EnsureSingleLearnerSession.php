<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleLearnerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user=$request->user();
        // Signing in must remain possible from a browser whose old login was replaced.
        if (!$user || $user->is_admin || strtolower(trim((string)$user->role)) !== 'student'
            || $request->is('api/auth/login','api/auth/register','api/auth/register/*')) {
            return $next($request);
        }
        $active=User::whereKey($user->id)->value('active_login_token');
        if (!$active) {
            // Adopt one pre-deployment session atomically; competing legacy sessions lose.
            $token=bin2hex(random_bytes(32));
            if (User::whereKey($user->id)->whereNull('active_login_token')->update(['active_login_token'=>$token])) {
                $request->session()->put('learner_login_token',$token);
                $active=$token;
            } else {
                $active=User::whereKey($user->id)->value('active_login_token');
            }
        }
        $token=$request->session()->get('learner_login_token');
        if (!is_string($active) || !is_string($token) || !hash_equals($active,$token)) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success'=>false,'code'=>'session_replaced','message'=>'You were signed out because this learner account was signed in on another browser or device.'],401);
            }
            return redirect('/');
        }
        return $next($request);
    }
}
