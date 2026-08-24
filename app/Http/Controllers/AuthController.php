<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PendingRegistration;
use App\Models\PendingEmailChange;
use App\Mail\RegistrationVerificationCode;
use App\Mail\EmailChangeVerificationCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('pending_email_changes', 'new_email')->ignore($user->id, 'user_id'),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $newEmail = strtolower(trim($data['email']));
        $changesEmail = $newEmail !== strtolower((string) $user->email);
        $changesPassword = filled($data['password'] ?? null);
        if (($changesEmail || $changesPassword) && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is required and must be correct to change your email or password.']);
        }

        $changed = [];
        $requiresEmailVerification = $changesEmail && $user->role === 'student';
        if ($changesEmail) $changed[] = $requiresEmailVerification ? 'email change requested' : 'email';
        if ((string) $user->phone !== (string) $data['phone']) $changed[] = 'phone number';
        if ($changesPassword) $changed[] = 'password';
        if ($changesEmail && ! $requiresEmailVerification) $user->email = $newEmail;
        $user->phone = trim($data['phone']);
        if ($changesPassword) $user->password = Hash::make($data['password']);
        $user->save();

        if ($requiresEmailVerification) {
            $rateKey = 'email-change:'.$user->id;
            if (RateLimiter::tooManyAttempts($rateKey, 5)) {
                return response()->json(['success' => false, 'message' => 'Too many email verification requests. Please try again later.'], 429);
            }
            RateLimiter::hit($rateKey, 60);
            $code = (string) random_int(100000, 999999);
            PendingEmailChange::updateOrCreate(['user_id' => $user->id], [
                'new_email' => $newEmail,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'last_sent_at' => now(),
            ]);
            Mail::to($newEmail)->send(new EmailChangeVerificationCode($code, $user->first_name ?: $user->name));
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => ucfirst(strtolower((string) ($user->role ?: 'user'))) . ' Settings Updated',
            'description' => $changed ? 'Updated ' . implode(', ', $changed) . '.' : 'Settings saved without changes.',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'verification_required' => $requiresEmailVerification,
            'pending_email' => $requiresEmailVerification ? $newEmail : null,
            'message' => $requiresEmailVerification ? 'Your other settings were saved. Enter the six-digit code sent to your new email address.' : 'Your settings have been updated.',
            'user' => ['email' => $user->email, 'phone' => $user->phone],
        ]);
    }

    public function verifyEmailChange(Request $request)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $user = Auth::user();
        $pending = PendingEmailChange::where('user_id', $user->id)->first();
        if (! $pending || $pending->expires_at->isPast()) return response()->json(['success' => false, 'message' => 'The verification code has expired. Request a new code.'], 422);
        if ($pending->attempts >= 5) return response()->json(['success' => false, 'message' => 'Too many incorrect attempts. Request a new code.'], 429);
        if (! Hash::check($data['code'], $pending->code_hash)) {
            $pending->increment('attempts');
            return response()->json(['success' => false, 'message' => 'The verification code is incorrect.'], 422);
        }
        if (User::where('email', $pending->new_email)->whereKeyNot($user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'That email address is already in use.'], 422);
        }

        $newEmail = $pending->new_email;
        DB::transaction(function () use ($user, $pending, $newEmail) {
            $user->update(['email' => $newEmail, 'email_verified_at' => now()]);
            $pending->delete();
        });
        RateLimiter::clear('email-change:'.$user->id);
        AuditLog::create(['user_id' => $user->id, 'action' => 'Learner Email Changed', 'description' => 'Verified and changed account email address.', 'ip_address' => $request->ip()]);

        return response()->json(['success' => true, 'message' => 'Your new email address has been verified and saved.', 'user' => ['email' => $newEmail, 'phone' => $user->phone]]);
    }

    public function resendEmailChangeCode(Request $request)
    {
        $user = Auth::user();
        $pending = PendingEmailChange::where('user_id', $user->id)->firstOrFail();
        if ($pending->last_sent_at->gt(now()->subMinute())) return response()->json(['success' => false, 'message' => 'Please wait one minute before requesting another code.'], 429);
        $code = (string) random_int(100000, 999999);
        $pending->update(['code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'last_sent_at' => now()]);
        Mail::to($pending->new_email)->send(new EmailChangeVerificationCode($code, $user->first_name ?: $user->name));
        return response()->json(['success' => true, 'message' => 'A new verification code was sent.']);
    }

    public function register(Request $request)
    {
        $request->validate([
            'su-fname' => 'required|string|max:255',
            'su-lname' => 'required|string|max:255',
            'su-email' => 'required|string|email|max:255|unique:users,email',
            'su-bdate' => 'required|string',
            'su-afftype' => 'required|string',
            'su-affname' => 'required|string|max:255',
            'su-phone' => 'required|string|max:255',
            'su-country' => 'required|in:PH,INTL',
            'su-password' => 'required|string|min:8',
        ]);
        $email = strtolower(trim($request->input('su-email')));
        $rateKey = 'registration-code:'.$request->ip().':'.$email;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) return response()->json(['success'=>false,'message'=>'Too many verification requests. Please try again later.'], 429);
        RateLimiter::hit($rateKey, 60);
        $code = (string) random_int(100000, 999999);
        PendingRegistration::updateOrCreate(['email'=>$email], [
            'registration_data'=>[
                'first_name'=>$request->input('su-fname'),'last_name'=>$request->input('su-lname'),
                'birthdate'=>$request->input('su-bdate'),'affiliation_type'=>$request->input('su-afftype'),
                'affiliation_name'=>$request->input('su-affname'),'phone'=>$request->input('su-phone'),
                'country_code'=>$request->input('su-country'),'password'=>$request->input('su-password'),
            ],
            'code_hash'=>Hash::make($code),'attempts'=>0,'expires_at'=>now()->addMinutes(10),'last_sent_at'=>now(),
        ]);
        Mail::to($email)->send(new RegistrationVerificationCode($code, $request->input('su-fname')));
        return response()->json(['success'=>true,'verification_required'=>true,'email'=>$email,'message'=>'We sent a six-digit verification code to your email.']);
    }

    public function verifyRegistration(Request $request)
    {
        $data = $request->validate(['email'=>'required|email','code'=>'required|digits:6']);
        $email = strtolower(trim($data['email']));
        $pending = PendingRegistration::where('email',$email)->first();
        if (!$pending || $pending->expires_at->isPast()) return response()->json(['success'=>false,'message'=>'The verification code has expired. Request a new code.'],422);
        if ($pending->attempts >= 5) return response()->json(['success'=>false,'message'=>'Too many incorrect attempts. Request a new code.'],429);
        if (!Hash::check($data['code'],$pending->code_hash)) { $pending->increment('attempts'); return response()->json(['success'=>false,'message'=>'The verification code is incorrect.'],422); }
        $registration = $pending->registration_data;
        $user = DB::transaction(function () use ($pending,$registration,$email) {
            $user = User::create(['name'=>$registration['first_name'].' '.$registration['last_name'],'first_name'=>$registration['first_name'],'last_name'=>$registration['last_name'],'email'=>$email,'email_verified_at'=>now(),'password'=>Hash::make($registration['password']),'phone'=>$registration['phone'],'country_code'=>$registration['country_code'],'birthdate'=>$registration['birthdate'],'role'=>'student','affiliation_type'=>$registration['affiliation_type'],'affiliation_name'=>$registration['affiliation_name'],'is_admin'=>false,'is_active'=>true]);
            $pending->delete(); return $user;
        });
        Auth::login($user); $request->session()->regenerate();
        AuditLog::create(['user_id'=>$user->id,'action'=>'Registration','description'=>$user->name.' verified their email and registered a learner account.','ip_address'=>$request->ip()]);
        return response()->json(['success'=>true,'message'=>'Email verified. Your learner account is ready.','user'=>['name'=>$user->name,'firstName'=>$user->first_name,'lastName'=>$user->last_name,'email'=>$user->email,'role'=>'student','affName'=>$user->affiliation_name,'phone'=>$user->phone,'countryCode'=>$user->country_code,'hasCertificate'=>false,'isSubscribed'=>false]]);
    }

    public function resendRegistrationCode(Request $request)
    {
        $email = strtolower(trim($request->validate(['email'=>'required|email'])['email']));
        $pending = PendingRegistration::where('email',$email)->firstOrFail();
        if ($pending->last_sent_at->gt(now()->subMinute())) return response()->json(['success'=>false,'message'=>'Please wait one minute before requesting another code.'],429);
        $code=(string)random_int(100000,999999); $pending->update(['code_hash'=>Hash::make($code),'attempts'=>0,'expires_at'=>now()->addMinutes(10),'last_sent_at'=>now()]);
        Mail::to($email)->send(new RegistrationVerificationCode($code,$pending->registration_data['first_name']));
        return response()->json(['success'=>true,'message'=>'A new verification code was sent.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($request->input('email')));
        $password = $request->input('password');
        $rateKey = 'login:'.$request->ip().':'.$email;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($rateKey).' seconds.',
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($rateKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'The provided email or password is incorrect.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.'
            ], 403);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before signing in.'
            ], 403);
        }

        RateLimiter::clear($rateKey);
        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'Login',
            'description' => $user->name . ' logged into the application.',
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Welcome back!',
            'user' => [
                'name' => $user->name,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'bdate' => $user->birthdate,
                'role' => $user->role,
                'affName' => $user->affiliation_name,
                'phone' => $user->phone,
                'progressPercentage' => (int) $user->progress_percentage,
                'modulesCompletedCount' => (int) $user->modules_completed_count,
                'examStatus' => $user->exam_status,
                'isCourseUnlocked' => (bool) $user->is_course_unlocked,
                'hasCertificate' => \App\Models\Certificate::where('user_id', $user->id)->exists(),
                'isSubscribed' => $user->isSubscribed(),
                'subscriptionExpiresAt' => $user->subscription_expires_at ? $user->subscription_expires_at->toIso8601String() : null,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'Logout',
                'description' => $user->name . ' logged out.',
                'ip_address' => $request->ip()
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    public function session()
    {
        $user = Auth::user();
        if ($user) {
            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'user' => null,
                    'message' => 'Account is inactive.'
                ]);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'name' => $user->name,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email,
                    'bdate' => $user->birthdate,
                    'role' => $user->role,
                    'affName' => $user->affiliation_name,
                    'phone' => $user->phone,
                    'progressPercentage' => (int) $user->progress_percentage,
                    'modulesCompletedCount' => (int) $user->modules_completed_count,
                    'examStatus' => $user->exam_status,
                    'isCourseUnlocked' => (bool) $user->is_course_unlocked,
                    'hasCertificate' => \App\Models\Certificate::where('user_id', $user->id)->exists(),
                    'isSubscribed' => $user->isSubscribed(),
                    'subscriptionExpiresAt' => $user->subscription_expires_at ? $user->subscription_expires_at->toIso8601String() : null,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'user' => null
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email'
        ]);

        $email = strtolower($request->input('email'));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email.'
            ], 404);
        }

        // Return a mock reset response since we are running in local/XAMPP environment without a mail server.
        // We will do a funny/helpful response similar to the frontend's original action:
        return response()->json([
            'success' => true,
            'message' => 'An email search was performed. For security, passwords are encrypted, but in this development build, we can confirm the account exists! (Password recovery link has been simulated).'
        ]);
    }
}
