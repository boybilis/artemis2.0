<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminPasswordController extends Controller
{
    private function portalUser(string $email): ?User
    {
        $user = User::where('email', strtolower(trim($email)))->first();
        return $user && ($user->is_admin || in_array(strtolower(trim($user->role ?? '')), ['admin', 'assistant_admin', 'instructor', 'encoder', 'staff'], true)) ? $user : null;
    }

    public function requestForm()
    {
        return view('admin.auth.password', ['reset' => false]);
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        if ($user = $this->portalUser($request->input('email'))) {
            Password::broker()->sendResetLink(['email' => $user->email], function ($user, $token) {
                $user->notify(new AdminResetPassword($token));
            });
        }
        return back()->with('status', 'If this email belongs to a management account, a password reset link will be sent. Please check your inbox and spam folder.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('admin.auth.password', ['reset' => true, 'token' => $token, 'email' => $request->query('email', '')]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);
        $user = $this->portalUser($request->input('email'));
        $status = $user ? Password::broker()->reset([
            'email' => $user->email, 'token' => $request->input('token'),
            'password' => $request->input('password'), 'password_confirmation' => $request->input('password_confirmation'),
        ], function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        }) : Password::INVALID_TOKEN;
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or expired. Please request a new link.'])->withInput($request->only('email'));
        }
        return redirect()->route('admin.login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
