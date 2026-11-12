<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Topic;
use App\Models\Subtopic;
use App\Models\Course;
use App\Models\Subject;
use App\Models\CourseEnrollment;
use App\Models\CourseBatch;
use App\Models\ReviewPackage;
use App\Models\BatchZoomSession;
use App\Models\Branch;

use App\Models\QuizQuestion;
use App\Models\UserProgress;
use App\Models\QuizAttempt;
use App\Models\Voucher;
use App\Models\PaymentTransaction;
use App\Models\Certificate;
use App\Models\Announcement;
use App\Models\AuditLog;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function packages()
    {
        $packages = ReviewPackage::with('batches.courses')->latest()->get();
        $batches = CourseBatch::available()->with('courses')->orderBy('starts_at')->orderBy('name')->get();
        return view('admin.packages.index', compact('packages', 'batches'));
    }

    public function storePackage(Request $request)
    {
        $data = $this->validatePackage($request);
        $batchIds = $data['batch_ids'];
        unset($data['batch_ids']);
        $package = DB::transaction(function () use ($data, $batchIds) {
            $package = ReviewPackage::create($data + ['created_by' => Auth::id()]);
            $package->batches()->sync($batchIds);
            return $package;
        });
        AuditLog::create(['user_id'=>Auth::id(), 'action'=>'Package Created', 'description'=>'Created review package '.$package->name, 'ip_address'=>$request->ip()]);
        return redirect()->route('admin.packages.index')->with('success', 'Review package created.');
    }

    public function updatePackage(Request $request, ReviewPackage $package)
    {
        $data = $this->validatePackage($request);
        $batchIds = $data['batch_ids'];
        unset($data['batch_ids']);
        DB::transaction(function () use ($package, $data, $batchIds) {
            $package->update($data);
            $package->batches()->sync($batchIds);
        });
        return redirect()->route('admin.packages.index')->with('success', 'Review package updated.');
    }

    public function destroyPackage(Request $request, ReviewPackage $package)
    {
        abort_if($package->transactions()->where('status', 'paid')->exists(), 422, 'A package with successful payments cannot be deleted. Set it to draft instead.');
        $name = $package->name;
        $package->delete();
        AuditLog::create(['user_id'=>Auth::id(), 'action'=>'Package Deleted', 'description'=>'Deleted review package '.$name, 'ip_address'=>$request->ip()]);
        return redirect()->route('admin.packages.index')->with('success', 'Review package deleted.');
    }

    private function validatePackage(Request $request): array
    {
        $data = $request->validate([
            'name'=>'required|string|max:255', 'description'=>'nullable|string|max:3000', 'price'=>'required|numeric|min:0',
            'starts_at'=>'required|date', 'class_type'=>'required|in:Live Online,Face to face,Full Online',
            'status'=>'required|in:draft,active', 'batch_ids'=>'required|array|min:1', 'batch_ids.*'=>'integer|exists:course_batches,id',
        ]);
        $batchIds = collect($data['batch_ids'])->map(fn ($id) => (int) $id)->unique();
        abort_unless(CourseBatch::available()->whereIn('id', $batchIds)->count() === $batchIds->count(), 422, 'Packages may include only active batch offerings.');
        $data['batch_ids'] = $batchIds->values()->all();
        return $data;
    }

    // ─── AUTHENTICATION ──────────────────────────────────────────
    public function showLogin()
    {
        if (Auth::check() && (Auth::user()->is_admin || in_array(trim(strtolower(Auth::user()->role)), ['admin', 'assistant_admin', 'instructor', 'encoder', 'staff'], true))) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->is_admin || in_array(trim(strtolower($user->role)), ['admin', 'assistant_admin', 'instructor', 'encoder', 'staff'], true)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'Admin Login',
                    'description' => ucfirst($user->role ?: 'Administrator') . ' logged into the dashboard.',
                    'ip_address' => $request->ip()
                ]);
                return redirect()->intended(route('admin.dashboard'));
            } else {
                Auth::logout();
                return back()->withErrors(['email' => 'This account does not have access to the management portal.']);
            }
        }

        return back()->withErrors(['email' => 'Invalid email or password.']);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'Admin Logout',
                'description' => 'Administrator logged out.',
                'ip_address' => $request->ip()
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    // ─── DASHBOARD OVERVIEW ──────────────────────────────────────
    public function dashboard(Request $request)
    {
        $recentUsersQuery = User::query();
        if (in_array(trim(strtolower(Auth::user()->role)), ['instructor', 'staff'], true) && !Auth::user()->is_admin) {
            $recentUsersQuery->where(fn ($query) => $query->where('role', 'student')->orWhereNull('role'));
        }
        $latestUsers = (clone $recentUsersQuery)->select('id', 'name', 'role', 'is_admin', 'created_at')
            ->latest()->orderByDesc('id')->limit(10);
        $recentUsers = User::query()->fromSub($latestUsers, 'users')->latest()->orderByDesc('id')
            ->paginate(5, ['id', 'name', 'role', 'is_admin'], 'dashboard_page');
        if ($request->ajax() && $request->has('dashboard_page')) {
            return view('admin.partials.dashboard-recent-users', compact('recentUsers'));
        }

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $totalUsers = User::count();
        $activeUsers = User::whereIn('id', AuditLog::query()
            ->select('user_id')
            ->whereIn('action', ['Login', 'Admin Login'])
            ->whereBetween('created_at', [$monthStart, $now]))->count();
        $newThisMonth = User::whereBetween('created_at', [$monthStart, $now])->count();

        // Snapshot stats
        $stats = [
            ['label' => 'Total users', 'value' => number_format($totalUsers), 'note' => '+' . number_format($newThisMonth) . ' this month'],
            ['label' => 'Active users', 'value' => number_format($activeUsers), 'note' => 'Signed in this calendar month'],
            ['label' => 'Total courses', 'value' => number_format(Course::count()), 'note' => 'Master courses'],
            ['label' => 'Total class batches', 'value' => number_format(CourseBatch::count()), 'note' => 'All class batches'],
            ['label' => 'Certificates issued', 'value' => number_format(Certificate::count()), 'note' => 'Verified completions'],
        ];

        if (Auth::user()->hasAdminAccess()) {
            $stats[] = ['label' => 'Total branches', 'value' => number_format(Branch::count()), 'note' => 'All registered branches'];
            $issuedVouchers = Voucher::where(fn ($query) => $query->where('used', true)
                ->orWhere('status', 'active')->orWhereNull('status'));
            $paidRevenue = PaymentTransaction::where('status', 'paid')->where('currency', 'PHP')->sum('amount');
            // Older paid batch vouchers may not have a corresponding payment transaction.
            $legacyRevenue = Voucher::where('payment_provider', 'paymongo')->where('used', true)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('payment_transactions')
                    ->where(fn ($match) => $match->whereColumn('payment_transactions.provider_checkout_id', 'vouchers.provider_checkout_id')
                        ->orWhereColumn('payment_transactions.provider_payment_id', 'vouchers.provider_payment_id')))
                ->sum('price');
            $stats[] = ['label' => 'Vouchers issued', 'value' => number_format($issuedVouchers->count()), 'note' => 'Available and redeemed codes'];
            $stats[] = ['label' => 'Total revenue', 'value' => 'PHP ' . number_format($paidRevenue + $legacyRevenue, 2), 'note' => 'Lifetime confirmed payments'];
        }

        $yearStart = $now->copy()->startOfYear();
        $registrationsQuery = (clone $recentUsersQuery)->whereBetween('created_at', [$yearStart, $now]);
        $monthlyRegistrations = [];
        for ($i = 0; $i < $now->month; $i++) {
            $month = $yearStart->copy()->addMonths($i);
            $registrationsQuery->selectRaw("COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS month_{$i}", [
                $month, $month->copy()->addMonth(),
            ]);
            $monthlyRegistrations[] = ['month' => $month->format('M'), 'label' => $month->format('F Y')];
        }
        $counts = $registrationsQuery->toBase()->first();
        foreach ($monthlyRegistrations as $i => $registration) {
            $monthlyRegistrations[$i]['count'] = (int) $counts->{"month_{$i}"};
        }
        $registrationYear = $now->year;

        return view('admin.dashboard', compact('stats', 'recentUsers', 'monthlyRegistrations', 'registrationYear'));
    }

    // ─── USER MANAGEMENT ─────────────────────────────────────────
    private function userProfileData(User $user): array
    {
        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);

        return [
            'first_name' => $user->first_name ?: ($nameParts[0] ?? ''),
            'middle_name' => $user->middle_name,
            'last_name' => $user->first_name ? $user->last_name : ($nameParts[1] ?? ''),
            'email' => $user->email,
            'role' => $user->isAssistantAdmin() ? 'Asst. Admin' : ($user->hasAdminAccess() ? 'Admin' : ucfirst($user->role ?: 'student')),
            'status' => $user->is_active ? 'Active' : 'Inactive',
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'birthdate' => $user->birthdate ? substr((string) $user->birthdate, 0, 10) : null,
            'affiliation_type' => $user->affiliation_type,
            'affiliation_name' => $user->affiliation_name,
            'profile_picture_url' => app(\App\Services\ProfilePictures::class)->url($user),
        ];
    }

    public function userProfile(Request $request, User $user)
    {
        abort_unless($request->user()->hasAdminAccess(), 403);

        return response()->json(['profile' => $this->userProfileData($user)])->header('Cache-Control', 'no-store');
    }

    public function updateUserProfile(Request $request, User $user)
    {
        abort_unless($request->user()->hasAdminAccess(), 403);
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'present|nullable|string|max:255',
            'phone' => 'present|nullable|string|max:50',
            'country_code' => 'required|in:PH,INTL',
            'birthdate' => 'present|nullable|date_format:Y-m-d|before_or_equal:today',
            'affiliation_type' => 'present|nullable|string|max:255',
            'affiliation_name' => 'present|nullable|string|max:255',
            'email' => 'missing',
            'password' => 'missing',
            'role' => 'missing',
            'profile_picture' => ['sometimes', ...\App\Services\ProfilePictures::RULES],
        ]);
        $middleName = array_key_exists('middle_name', $data) ? $data['middle_name'] : $user->middle_name;
        $data['name'] = implode(' ', array_filter([$data['first_name'], $middleName, $data['last_name']], fn ($part) => filled($part)));
        if (mb_strlen($data['name']) > 255) {
            throw \Illuminate\Validation\ValidationException::withMessages(['first_name' => 'The full name may not exceed 255 characters.']);
        }
        $pictures = app(\App\Services\ProfilePictures::class);
        unset($data['profile_picture']);
        if ($request->hasFile('profile_picture')) {
            $pictures->save($user, $request->file('profile_picture'), $data);
        } else {
            $user->update($data);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'User Profile Updated',
            'description' => "Updated profile for user ID {$user->id}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'User profile updated.', 'name' => $user->name, 'profile_picture_url' => $pictures->url($user)]);
    }

    public function users(Request $request)
    {
        $query = User::query();

        if (in_array(trim(strtolower(Auth::user()->role)), ['instructor', 'staff'], true) && !Auth::user()->is_admin) {
            $query->where(function($q) {
                $q->where('role', 'student')->orWhereNull('role');
            });
        }

        if ($request->query('table_data') === 'users') {
            return response()->json(['rows'=>$query->latest()->get(['id','name','email','role','is_admin','affiliation_name','is_active'])->map(fn($u)=>[
                'id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'affiliation'=>$u->affiliation_name ?: 'N/A',
                'role'=>$u->is_admin ? 'admin' : ($u->role ?: 'student'),'active'=>(bool)$u->is_active,
                'toggleUrl'=>route('admin.users.toggle',$u),'roleUrl'=>route('admin.users.role',$u),
                'profileUrl'=>Auth::user()->hasAdminAccess() ? route('admin.users.show',$u) : null,
            ]),'canEditRoles'=>!Auth::user()->isAssistantAdmin() && Auth::user()->hasAdminAccess()]);
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('affiliation_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'Active') {
                $query->where('is_active', true);
            } elseif ($status === 'Inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('affiliation')) {
            $query->where('affiliation_type', $request->input('affiliation'));
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);
        
        $totalTopicsCount = Topic::count() ?: 1;

        $users->getCollection()->transform(function ($u) use ($totalTopicsCount) {
            $completed = UserProgress::where('user_id', $u->id)->count();
            $progressPct = round(($completed / $totalTopicsCount) * 100);
            
            $quizzesCount = QuizAttempt::where('user_id', $u->id)->whereNotNull('topic_id')->count();
            $examsCount = QuizAttempt::where('user_id', $u->id)->whereNull('topic_id')->count();
            
            $cert = Certificate::where('user_id', $u->id)->first();
            $certificateStatus = $cert ? 'Issued (' . $cert->code . ')' : 'Not issued — Mock Exam not passed';

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->is_admin ? 'Admin' : ucfirst($u->role ?? 'Student'),
                'affiliation' => $u->affiliation_name ?: 'N/A',
                'progress' => $progressPct,
                'quizzes' => $quizzesCount,
                'exams' => $examsCount,
                'certificate' => $certificateStatus,
                'status' => $u->is_active ? 'Active' : 'Inactive',
            ];
        });

        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count()
        ];

        return view('admin.users.index', ['users' => $users, 'stats' => $stats]);
    }

    public function showUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if (strtolower((string) Auth::user()->role) === 'staff' && strtolower((string) $user->role) !== 'student') abort(404);

        $enrollments = CourseEnrollment::where('user_id', $user->id)->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($request->ajax() && $request->header('X-Admin-Table') === 'user-course-progress') {
            $batch = (clone $enrollments)->where('batch_id', $request->integer('batch_id'))->firstOrFail()->batch;
            abort_unless($batch, 404);
            $course = $batch->courses()->where('courses.id', $request->integer('course_id'))->firstOrFail();
            $visibleSubjects = Subject::where('course_id', $course->id)->where('status', 'approved')
                ->tap(fn ($query) => $batch->filterSubjects($query))->select('id');
            $topics = Topic::where('course_id', $course->id)->where('status', 'approved')
                ->where(function ($query) use ($batch, $visibleSubjects) {
                    $query->whereIn('subject_id', $visibleSubjects);
                    if ($batch->includesUnassignedTopics()) $query->orWhereNull('subject_id');
                })->with('subject:id,title')->orderBy('subject_id')->orderBy('sort_order')->orderBy('id')
                ->paginate(10, ['id', 'title', 'subject_id'], 'topics_page')->withQueryString();
            $topicIds = $topics->getCollection()->pluck('id');
            $attempts = QuizAttempt::where('user_id', $user->id)->where('course_id', $course->id)->where('batch_id', $batch->id);
            $bestAttempts = (clone $attempts)->whereIn('topic_id', $topicIds)
                ->orderByDesc('score')->orderByDesc('id')->get(['id', 'topic_id', 'score', 'total'])->groupBy('topic_id');
            $completedIds = UserProgress::where('user_id', $user->id)->where('course_id', $course->id)
                ->whereIn('topic_id', $topicIds)
                ->whereNotIn('topic_id', app(\App\Services\SubjectPolicy::class)->unacceptedTopicIds($user->id, (int) $course->id))
                ->pluck('topic_id')->flip();
            $examAttempts = $attempts->whereNull('topic_id')->whereNull('subtopic_id')->latest()->orderByDesc('id')
                ->paginate(10, ['*'], 'exams_page')->withQueryString();
            $vouchers = Voucher::where('used_by', $user->id)->where('batch_id', $batch->id)
                ->latest()->orderByDesc('id')->paginate(10, ['*'], 'vouchers_page')->withQueryString();

            return view('admin.partials.user-course-progress', compact('course', 'batch', 'topics', 'bestAttempts', 'completedIds', 'examAttempts', 'vouchers'));
        }

        $enrolledCourses = (clone $enrollments)->with('batch.courses')->get()->flatMap(function (CourseEnrollment $enrollment) {
            $batch = $enrollment->batch;
            if (!$batch) return [];
            return $batch->courses->map(fn ($course) => [
                'batch_id' => $batch->id, 'course_id' => $course->id, 'label' => $course->title.' — '.$batch->name,
            ]);
        })->sortBy('label')->values();
        $cert = Certificate::where('user_id', $user->id)->first();
        $userStats = [
            'id' => $user->id,
            'name' => $user->name,
            'certificate' => $cert ? 'Issued ('.$cert->code.')' : 'Not issued — Mock Exam not passed',
            'status' => $user->is_active ? 'Active' : 'Inactive',
        ];
        $profile = $this->userProfileData($user);

        return view('admin.users.show', compact('userStats', 'profile', 'enrolledCourses'));
    }

    public function toggleUserStatus($id, Request $request)
    {
        $user = User::findOrFail($id);
        if (strtolower((string) Auth::user()->role) === 'staff' && strtolower((string) $user->role) !== 'student') abort(404);
        $user->is_active = !$user->is_active;
        $user->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $user->is_active ? 'Activate User' : 'Deactivate User',
            'description' => ($user->is_active ? 'Activated' : 'Deactivated') . ' learner account: ' . $user->email,
            'ip_address' => $request->ip()
        ]);

        if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>$user->is_active ? 'User activated.' : 'User disabled.']);
        return back();
    }

    public function updateUserRole($id, Request $request)
    {
        if (Auth::user()->isAssistantAdmin() || !Auth::user()->hasAdminAccess()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'role' => 'required|in:admin,assistant_admin,instructor,encoder,staff,student'
        ]);

        $user = User::findOrFail($id);
        $newRole = $request->input('role');
        
        $user->is_admin = ($newRole === 'admin');
        $user->role = $newRole;
        $user->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Role Update',
            'description' => "Changed role of {$user->email} to {$newRole}",
            'ip_address' => $request->ip()
        ]);

        if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>'User role updated.']);
        return back()->with('success', "User role successfully updated to " . ucfirst($newRole) . ".");
    }

    // ─── CONTENT MANAGEMENT ──────────────────────────────────────
    public function content()
    {
        $courses = Course::with(['topics'])->orderBy('created_at', 'desc')->get();
        $topics = \App\Models\Topic::all();
        return view('admin.content.index', compact('courses', 'topics'));
    }

    public function contentCourses()
    {
        $courses = Course::orderBy('created_at', 'desc')->get();
        return view('admin.content.courses', ['courses' => $courses, 'classManagement' => false]);
    }

    public function classManagement()
    {
        $courses = Course::available()->orderBy('title')->get();
        $batches = CourseBatch::with(['courses:id,title', 'courses.subjects:id,course_id,is_intensive_final_coaching'])
            ->withCount(['zoomSessions', 'enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'active')])
            ->orderByDesc('starts_at')->orderByDesc('id')->paginate(9)->withQueryString();
        return view('admin.classes.index', compact('courses', 'batches'));
    }

    public function storeClassBatch(Request $request)
    {
        $data = $this->validateClassBatch($request);
        $courseIds = $data['course_ids']; unset($data['course_ids']);
        $batch = DB::transaction(function () use ($data, $courseIds) {
            $batch = CourseBatch::create($data + ['course_id'=>$courseIds[0], 'created_by'=>Auth::id()]);
            $batch->courses()->sync($courseIds);
            return $batch;
        });
        if ($request->expectsJson()) return response()->json(['success'=>true, 'message'=>"{$batch->name} created."], 201);
        return redirect()->route('admin.classes.index')->with('success', "{$batch->name} created.");
    }

    public function quoteClassBatchPrice(Request $request, \App\Services\BatchExchangeRates $rates)
    {
        $data = $request->validate(['price'=>'required|numeric|min:0|max:99999999.99', 'currency'=>'required|in:'.implode(',', \App\Services\BatchExchangeRates::CURRENCIES)]);
        return response()->json($rates->quote($data['currency'], (float) $data['price']))->header('Cache-Control', 'no-store');
    }

    public function updateClassBatch(Request $request, CourseBatch $batch)
    {
        $data = $this->validateClassBatch($request, $batch->id);
        $courseIds = $data['course_ids']; unset($data['course_ids']);
        DB::transaction(function () use ($batch, $data, $courseIds) {
            $batch->update($data + ['course_id'=>$courseIds[0]]);
            $batch->courses()->sync($courseIds);
        });
        if ($request->expectsJson()) return response()->json(['success'=>true, 'message'=>'Batch updated.']);
        return redirect()->route('admin.classes.index')->with('success', 'Batch updated.');
    }

    public function destroyClassBatch(CourseBatch $batch)
    {
        abort_if($batch->enrollments()->exists(), 422, 'A batch with enrollment history cannot be deleted. Close it instead.');
        $batch->delete();
        return redirect()->route('admin.classes.index')->with('success', 'Batch deleted.');
    }

    private function validateClassBatch(Request $request, ?int $batchId = null): array
    {
        if (is_array($request->input('class_sessions'))) {
            $request->merge(['class_sessions'=>array_values(array_filter($request->input('class_sessions'), fn ($row) => !is_array($row) || array_filter($row, fn ($value) => filled($value))))]);
        }
        $data = $request->validate([
            'name'=>'required|string|max:255', 'code'=>'nullable|string|max:80',
            'description'=>'nullable|string|max:2000', 'starts_at'=>'nullable|date', 'ends_at'=>'nullable|date|after_or_equal:starts_at',
            'schedule_day'=>'nullable|string|max:30', 'start_time'=>'nullable|date_format:H:i', 'end_time'=>'nullable|date_format:H:i|after:start_time',
            'schedule_days_present'=>'sometimes|in:1',
            'schedule_days'=>'nullable|array|max:7',
            'schedule_days.*'=>'required|distinct|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'class_sessions'=>'sometimes|array|max:28',
            'class_sessions.*'=>'required|array:day,start_time,end_time',
            'class_sessions.*.day'=>'required|in:'.implode(',', CourseBatch::CLASS_DAYS),
            'class_sessions.*.start_time'=>'required|date_format:H:i',
            'class_sessions.*.end_time'=>'required|date_format:H:i|after:class_sessions.*.start_time',
            'international_currency'=>'sometimes|nullable|in:'.implode(',', \App\Services\BatchExchangeRates::CURRENCIES),
            'modality'=>'nullable|in:Online,Blended,Live via Zoom', 'price'=>'required|numeric|min:0|max:99999999.99', 'usd_price'=>'nullable|numeric|min:0',
            'capacity'=>'nullable|integer|min:1|max:100000', 'status'=>'required|in:draft,open,closed,completed',
            'includes_intensive_final_coaching'=>'nullable|boolean',
            'course_type'=>'sometimes|required|in:complete,comprehensive,final_coaching,mentoring',
            'course_ids'=>'required|array|min:1', 'course_ids.*'=>['integer',\Illuminate\Validation\Rule::exists('courses','id')->where('approval_status','approved')],
        ]);
        if ($request->has('schedule_days_present') || $request->has('schedule_days')) {
            $days = array_values(array_intersect(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], $data['schedule_days'] ?? []));
            $data['schedule_day'] = $this->legacyBatchDaySummary($days);
        }
        unset($data['schedule_days'], $data['schedule_days_present']);
        if (array_key_exists('international_currency', $data)) {
            $data['international_pricing'] = $data['international_currency']
                ? app(\App\Services\BatchExchangeRates::class)->quote($data['international_currency'], (float) $data['price']) : null;
            $data['usd_price'] = null;
            unset($data['international_currency']);
        }
        foreach (['starts_at'=>'00:00:00', 'ends_at'=>'23:59:59'] as $field => $time) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data[$field] ?? '')) {
                $data[$field] .= ' '.$time;
            }
        }
        if (array_key_exists('class_sessions', $data)) {
            $sessions = collect($data['class_sessions'])->sortBy(fn ($row) => [array_search($row['day'], CourseBatch::CLASS_DAYS, true), $row['start_time']])->values();
            $data['class_sessions'] = $sessions->map(fn ($row) => ['day'=>CourseBatch::CLASS_DAY_CODES[$row['day']]] + $row)->all();
            $data['schedule_day'] = $this->legacyBatchDaySummary($sessions->pluck('day')->unique()->all());
            $uniform = $sessions->map(fn ($row) => [$row['start_time'], $row['end_time']])->unique()->count() === 1;
            $data['start_time'] = $uniform ? $sessions->first()['start_time'] : null;
            $data['end_time'] = $uniform ? $sessions->first()['end_time'] : null;
        }
        $data['course_type'] = $data['course_type'] ?? ($request->boolean('includes_intensive_final_coaching') ? 'complete' : 'comprehensive');
        $data['includes_intensive_final_coaching'] = in_array($data['course_type'], ['complete', 'final_coaching'], true);

        if (!$batchId || blank($data['code'] ?? null)) {
            $base = Str::upper(Str::slug($data['name']));
            $base = Str::limit($base ?: 'BATCH', 70, '');
            $candidate = $base;
            $suffix = 2;
            while (CourseBatch::where('code', $candidate)->when($batchId, fn ($query) => $query->where('id', '!=', $batchId))->exists()) {
                $candidate = Str::limit($base, 70 - strlen((string) $suffix), '') . '-' . $suffix++;
            }
            $data['code'] = $candidate;
        }

        return $data;
    }

    private function legacyBatchDaySummary(array $days): ?string
    {
        if (!$days) return null;
        return implode(',', array_map(fn ($day) => CourseBatch::CLASS_DAY_CODES[$day], $days));
    }

    public function classBatchZoomSessions(CourseBatch $batch)
    {
        $courses = $batch->courses()->where('approval_status','approved')->with(['subjects'=>fn ($query)=>$query->where('status','approved')->tap(fn ($query)=>$batch->filterSubjects($query))->with(['topics'=>fn ($query)=>$query->where('status','approved')->orderBy('sort_order')])])->get();
        return response()->json(['success'=>true,'batch'=>['id'=>$batch->id,'name'=>$batch->name,'code'=>$batch->code], 'courses'=>$courses->map(fn ($course)=>['id'=>$course->id,'title'=>$course->title,'subjects'=>$course->subjects->map(fn ($subject)=>['id'=>$subject->id,'title'=>$subject->title,'topics'=>$subject->topics->map(fn ($topic)=>['id'=>$topic->id,'title'=>$topic->title])])]), 'sessions'=>$batch->zoomSessions()->with('creator:id,name')->get()->map(fn($session)=>$this->zoomSessionData($session))]);
    }

    public function storeClassBatchZoomSession(Request $request, CourseBatch $batch)
    {
        $session = $batch->zoomSessions()->create($this->validateZoomSession($request, $batch)+['created_by'=>Auth::id()]);
        return response()->json(['success'=>true,'session'=>$this->zoomSessionData($session)]);
    }

    public function updateClassBatchZoomSession(Request $request, CourseBatch $batch, BatchZoomSession $session)
    {
        abort_unless((int)$session->batch_id === (int)$batch->id, 404);
        $session->update($this->validateZoomSession($request, $batch));
        return response()->json(['success'=>true,'session'=>$this->zoomSessionData($session)]);
    }

    public function destroyClassBatchZoomSession(CourseBatch $batch, BatchZoomSession $session)
    {
        abort_unless((int)$session->batch_id === (int)$batch->id, 404);
        $session->delete();
        return response()->json(['success'=>true]);
    }

    public function courseBatches($courseId)
    {
        $course = Course::findOrFail($courseId);
        $batches = CourseBatch::forCourse($course->id)->withCount(['enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'active')])->orderByDesc('starts_at')->orderByDesc('id')->get();
        return response()->json(['success'=>true,'course'=>['id'=>$course->id,'title'=>$course->title],'batches'=>$batches->map(fn ($batch) => [
            'id'=>$batch->id,'courseId'=>$batch->course_id,'name'=>$batch->name,'code'=>$batch->code,'description'=>$batch->description,
            'startsAt'=>$batch->starts_at?->format('Y-m-d\TH:i'),'endsAt'=>$batch->ends_at?->format('Y-m-d\TH:i'),
            'scheduleDay'=>$batch->schedule_day,'startTime'=>$batch->start_time,'endTime'=>$batch->end_time,'modality'=>$batch->modality,
            'price'=>$batch->price,'usdPrice'=>$batch->usd_price,'capacity'=>$batch->capacity,
            'status'=>$batch->status,'enrolledCount'=>$batch->active_enrollments_count,
        ])]);
    }

    public function storeCourseBatch(Request $request, $courseId)
    {
        abort_unless(Auth::user()->is_admin || in_array(strtolower((string) Auth::user()->role), ['admin', 'assistant_admin', 'staff'], true), 403, 'Only administrators and staff can create batches.');
        $request->validate(['course_id'=>'required|integer|exists:courses,id']);
        $course = Course::findOrFail($request->integer('course_id'));
        $data = $request->validate(['name'=>'required|string|max:255','code'=>'required|string|max:80|unique:course_batches,code','description'=>'nullable|string|max:2000','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','schedule_day'=>'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday','start_time'=>'nullable|date_format:H:i','end_time'=>'nullable|date_format:H:i|after:start_time','modality'=>'nullable|in:Online,Blended,Live via Zoom','price'=>'required|numeric|min:0','usd_price'=>'nullable|numeric|min:0','capacity'=>'nullable|integer|min:1|max:100000','status'=>'required|in:draft,open,closed,completed']);
        $batch = $course->batches()->create($data + ['created_by'=>Auth::id()]);
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Course Batch Created','description'=>"Created {$batch->name} for {$course->title}.",'ip_address'=>$request->ip()]);
        return response()->json(['success'=>true,'message'=>'Batch created successfully.']);
    }

    public function updateCourseBatch(Request $request, $courseId, $batchId)
    {
        abort_unless(Auth::user()->is_admin || in_array(strtolower((string) Auth::user()->role), ['admin', 'assistant_admin', 'staff'], true), 403, 'Only administrators and staff can edit batches.');
        $course = Course::findOrFail($courseId);
        $batch = CourseBatch::forCourse($course->id)->findOrFail($batchId);
        $request->validate(['course_id'=>'required|integer|exists:courses,id']);
        $data = $request->validate(['name'=>'required|string|max:255','code'=>'required|string|max:80|unique:course_batches,code,'.$batch->id,'description'=>'nullable|string|max:2000','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','schedule_day'=>'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday','start_time'=>'nullable|date_format:H:i','end_time'=>'nullable|date_format:H:i|after:start_time','modality'=>'nullable|in:Online,Blended,Live via Zoom','price'=>'required|numeric|min:0','usd_price'=>'nullable|numeric|min:0','capacity'=>'nullable|integer|min:1|max:100000','status'=>'required|in:draft,open,closed,completed']);
        $batch->update($data + ['course_id'=>$request->integer('course_id')]);
        $batch->courses()->syncWithoutDetaching([$request->integer('course_id')]);
        return response()->json(['success'=>true,'message'=>'Batch updated successfully.']);
    }

    public function batchZoomSessions($courseId, $batchId)
    {
        $batch = CourseBatch::forCourse($courseId)->findOrFail($batchId);
        return response()->json(['success'=>true, 'batch'=>['id'=>$batch->id, 'name'=>$batch->name, 'code'=>$batch->code],
            'sessions'=>$batch->zoomSessions()->with('creator:id,name')->get()->map(fn ($session) => $this->zoomSessionData($session))]);
    }

    public function storeBatchZoomSession(Request $request, $courseId, $batchId)
    {
        $batch = CourseBatch::forCourse($courseId)->findOrFail($batchId);
        $session = $batch->zoomSessions()->create($this->validateZoomSession($request, $batch) + ['created_by'=>Auth::id()]);
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Zoom Session Created','description'=>"Scheduled {$session->title} for {$batch->name}.",'ip_address'=>$request->ip()]);
        return response()->json(['success'=>true, 'message'=>'Zoom session scheduled.', 'session'=>$this->zoomSessionData($session)]);
    }

    public function updateBatchZoomSession(Request $request, $courseId, $batchId, $sessionId)
    {
        $batch = CourseBatch::forCourse($courseId)->findOrFail($batchId);
        $session = $batch->zoomSessions()->findOrFail($sessionId);
        $session->update($this->validateZoomSession($request, $batch));
        return response()->json(['success'=>true, 'message'=>'Zoom session updated.', 'session'=>$this->zoomSessionData($session)]);
    }

    public function destroyBatchZoomSession(Request $request, $courseId, $batchId, $sessionId)
    {
        $batch = CourseBatch::forCourse($courseId)->findOrFail($batchId);
        $session = $batch->zoomSessions()->findOrFail($sessionId);
        $title = $session->title;
        $session->delete();
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Zoom Session Deleted','description'=>"Deleted {$title} from {$batch->name}.",'ip_address'=>$request->ip()]);
        return response()->json(['success'=>true, 'message'=>'Zoom session deleted.']);
    }

    private function validateZoomSession(Request $request, CourseBatch $batch): array
    {
        $data = $request->validate([
            'title'=>'required|string|max:255', 'description'=>'nullable|string|max:3000',
            'zoom_url'=>'required|url|max:1000', 'recording_url'=>'nullable|url|max:1000', 'starts_at'=>'required|date',
            'ends_at'=>'nullable|date|after:starts_at', 'status'=>'required|in:scheduled,cancelled',
            'course_id'=>'nullable|integer', 'subject_id'=>'nullable|integer|required_with:course_id',
            'topic_id'=>'nullable|integer',
        ]);
        if (!empty($data['subject_id']) || !empty($data['course_id']) || !empty($data['topic_id'])) {
            $course = $batch->courses()->where('courses.id', $data['course_id'] ?? 0)->where('approval_status','approved')->first();
            if (!$course) throw \Illuminate\Validation\ValidationException::withMessages(['course_id'=>'Select an approved master course assigned to this batch.']);
            $subject = Subject::where('course_id',$course->id)->where('status','approved')->tap(fn ($query)=>$batch->filterSubjects($query))->find($data['subject_id'] ?? 0);
            if (!$subject) throw \Illuminate\Validation\ValidationException::withMessages(['subject_id'=>'Select an available subject in the selected master course.']);
            if (!empty($data['topic_id']) && !Topic::where('course_id',$course->id)->where('subject_id',$subject->id)->where('status','approved')->whereKey($data['topic_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['topic_id'=>'Select a topic in the selected subject.']);
            }
        }
        return $data;
    }

    private function zoomSessionData(BatchZoomSession $session): array
    {
        return ['id'=>$session->id, 'courseId'=>$session->course_id, 'subjectId'=>$session->subject_id, 'topicId'=>$session->topic_id, 'title'=>$session->title, 'description'=>$session->description,
            'zoomUrl'=>$session->zoom_url, 'recordingUrl'=>$session->recording_url, 'startsAt'=>$session->starts_at?->toIso8601String(),
            'endsAt'=>$session->ends_at?->toIso8601String(), 'status'=>$session->status,
            'createdBy'=>$session->creator?->name];
    }

    public function reassignEnrollmentBatch(Request $request, $courseId, $userId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate(['batch_id'=>'required|integer|exists:course_batches,id']);
        $batch = CourseBatch::forCourse($course->id)->findOrFail($data['batch_id']);
        $enrollment = CourseEnrollment::whereHas('batch.courses', fn ($query) => $query->where('courses.id', $course->id))->where('user_id', $userId)->firstOrFail();
        if ($batch->capacity && $enrollment->batch_id !== $batch->id && $batch->enrollments()->where('status', 'active')->count() >= $batch->capacity) {
            return response()->json(['success'=>false,'message'=>'This batch has reached its enrollment capacity.'], 422);
        }
        $enrollment->update(['batch_id'=>$batch->id]);
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Learner Batch Reassigned','description'=>"Assigned learner ID {$userId} to {$batch->name} in {$course->title}.",'ip_address'=>$request->ip()]);
        return response()->json(['success'=>true,'message'=>'Learner batch assignment updated.']);
    }

    public function courseEnrollments(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $batch = CourseBatch::forCourse($course->id)->findOrFail($request->integer('batch_id'));
        $search = trim((string) $request->input('search'));
        $enrollments = CourseEnrollment::with(['user:id,name,email','batch:id,name,code'])
            ->where('batch_id', $batch->id)
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('enrolled_at')->orderByDesc('id')->paginate(15);

        $assessments = collect();
        $topicIds = QuizQuestion::where('course_id', $course->id)->where('question_type', 'quiz')
            ->where('status', 'approved')->whereNotNull('topic_id')->pluck('topic_id')->unique();
        Topic::whereIn('id', $topicIds)->with('subject')->orderBy('sort_order')->get()->each(function ($topic) use ($assessments) {
            $scope = $topic->subject ? $topic->subject->subject_code . ' — ' : '';
            $assessments->push(['value' => 'topic_quiz:' . $topic->id, 'label' => $scope . $topic->title . ' — Topic Quiz', 'subjectId' => $topic->subject_id]);
        });
        Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))
            ->whereIn('content_type', ['pre_test', 'post_test', 'practice_test'])
            ->with('topic.subject')->orderBy('sort_order')->get()->each(function ($item) use ($assessments) {
                $type = ['pre_test'=>'Pre-Test','post_test'=>'Post-Test','practice_test'=>'Practice Test'][$item->content_type];
                $scope = $item->topic->subject ? $item->topic->subject->subject_code . ' — ' : '';
                $assessments->push(['value' => 'subtopic:' . $item->id, 'label' => $scope . $item->topic->title . ' — ' . $type, 'subjectId' => $item->topic->subject_id]);
            });
        if (QuizQuestion::where('course_id', $course->id)->where('question_type', 'final')->where('status', 'approved')->exists()) {
            $assessments->push(['value' => 'course_mock', 'label' => 'Course Mock Exam', 'subjectId' => null]);
        }

        return response()->json([
            'success' => true,
            'canUnenroll' => !Auth::user()->isAssistantAdmin() && Auth::user()->hasAdminAccess(),
            'course' => ['id' => $course->id, 'title' => $course->title],
            'batch' => ['id' => $batch->id, 'name' => $batch->name, 'code' => $batch->code],
            'subjects' => Subject::where('course_id', $course->id)->orderBy('sort_order')->get(['id','subject_code','title']),
            'students' => $enrollments->getCollection()->map(fn ($enrollment) => [
                'id' => $enrollment->user_id,
                'name' => $enrollment->user?->name ?? 'Deleted learner',
                'email' => $enrollment->user?->email ?? '',
                'status' => $enrollment->status,
                'batchId' => $enrollment->batch_id,
                'batchName' => $enrollment->batch?->name,
                'enrolledAt' => optional($enrollment->enrolled_at ?? $enrollment->created_at)->format('M d, Y h:i A'),
            ]),
            'assessments' => $assessments->values(),
            'batches' => CourseBatch::forCourse($course->id)->orderBy('name')->get(['course_batches.id','name','code','status']),
            'pagination' => ['currentPage'=>$enrollments->currentPage(),'lastPage'=>$enrollments->lastPage(),'total'=>$enrollments->total()],
        ]);
    }

    public function courseRankings(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $batch = CourseBatch::forCourse($course->id)->findOrFail($request->integer('batch_id'));
        $enrolledUsers = CourseEnrollment::with('user:id,name')
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->filter(fn ($enrollment) => $enrollment->user)
            ->keyBy('user_id');

        $rankings = QuizAttempt::where('course_id', $course->id)
            ->where('batch_id', $batch->id)
            ->where('assessment_type', 'final')
            ->whereIn('user_id', $enrolledUsers->keys())
            ->get()
            ->groupBy('user_id')
            ->map(function ($attempts, $userId) use ($enrolledUsers) {
                $best = $attempts->sort(function ($left, $right) {
                    $leftPossible = (float) ($left->points_possible ?: $left->total);
                    $rightPossible = (float) ($right->points_possible ?: $right->total);
                    $leftEarned = (float) ($left->points_earned ?? $left->score);
                    $rightEarned = (float) ($right->points_earned ?? $right->score);
                    $leftPercent = $leftPossible > 0 ? ($leftEarned / $leftPossible) * 100 : 0;
                    $rightPercent = $rightPossible > 0 ? ($rightEarned / $rightPossible) * 100 : 0;
                    return [$rightPercent, $rightEarned, $right->id] <=> [$leftPercent, $leftEarned, $left->id];
                })->first();
                $possible = (float) ($best->points_possible ?: $best->total);
                $earned = (float) ($best->points_earned ?? $best->score);

                return [
                    'userId' => (int) $userId,
                    'name' => $enrolledUsers->get($userId)->user->name,
                    'score' => $earned,
                    'total' => $possible,
                    'percentage' => $possible > 0 ? round(($earned / $possible) * 100, 2) : 0,
                    'passed' => (bool) $best->passed,
                ];
            })
            ->sort(fn ($left, $right) => [$right['percentage'], $right['score'], strtolower($left['name'])] <=> [$left['percentage'], $left['score'], strtolower($right['name'])])
            ->take(20)
            ->values()
            ->map(fn ($entry, $index) => ['rank' => $index + 1] + $entry);

        return response()->json([
            'success' => true,
            'course' => ['id' => $course->id, 'title' => $course->title],
            'batch' => ['id' => $batch->id, 'name' => $batch->name, 'code' => $batch->code],
            'rankings' => $rankings,
        ]);
    }

    public function unenrollCourseStudent(Request $request, $courseId, User $user)
    {
        $actor = Auth::user();
        abort_unless($actor->is_admin || in_array(strtolower((string) $actor->role), ['admin', 'assistant_admin', 'staff'], true), 403, 'Only administrators and staff can unenroll students.');
        $course = Course::findOrFail($courseId);
        $enrollment = CourseEnrollment::whereHas('batch.courses', fn ($query) => $query->where('courses.id', $course->id))->where('user_id', $user->id)->firstOrFail();
        $enrollment->delete();
        AuditLog::create([
            'user_id'=>$actor->id,
            'action'=>'Student Unenrolled',
            'description'=>"Removed {$user->name} ({$user->email}) from {$course->title}. Historical learning and assessment records were preserved.",
            'ip_address'=>$request->ip(),
        ]);
        return response()->json(['success'=>true,'message'=>"{$user->name} was unenrolled from {$course->title}."]);
    }

    public function resetCourseAssessmentAttempt(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate(['user_id'=>'required|integer|exists:users,id','assessment'=>'required|string|max:80','batch_id'=>'required|integer|exists:course_batches,id']);
        $batch = CourseBatch::forCourse($course->id)->findOrFail($data['batch_id']);
        abort_unless(CourseEnrollment::where('batch_id', $batch->id)->where('user_id', $data['user_id'])->exists(), 422, 'The learner is not enrolled in this batch.');

        $attempts = QuizAttempt::where('course_id', $course->id)->where('batch_id', $batch->id)->where('user_id', $data['user_id']);
        $assessmentLabel = '';
        if ($data['assessment'] === 'course_mock') {
            $attempts->where('assessment_type', 'final');
            $assessmentLabel = 'Course Mock Exam';
        } elseif (str_starts_with($data['assessment'], 'subtopic:')) {
            $subtopicId = (int) substr($data['assessment'], strlen('subtopic:'));
            $item = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))->findOrFail($subtopicId);
            $attempts->where('subtopic_id', $item->id);
            $assessmentLabel = $item->title;
        } elseif (str_starts_with($data['assessment'], 'topic_quiz:')) {
            $topicId = (int) substr($data['assessment'], strlen('topic_quiz:'));
            $topic = Topic::where('course_id', $course->id)->findOrFail($topicId);
            $attempts->where('topic_id', $topic->id)->where('assessment_type', 'quiz')->whereNull('subtopic_id');
            $assessmentLabel = $topic->title . ' Topic Quiz';
        } else {
            return response()->json(['success'=>false,'message'=>'Select a valid assessment.'], 422);
        }

        $deleted = $attempts->delete();
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Assessment Attempt Reset','description'=>"Reset {$assessmentLabel} attempts for learner ID {$data['user_id']} in {$course->title}.",'ip_address'=>$request->ip()]);
        return response()->json(['success'=>true,'message'=>$deleted ? 'The assessment attempt was reset. The learner may retake it.' : 'No saved attempt was found; the learner may take the assessment.','deletedAttempts'=>$deleted]);
    }

    public function updateMockExamSettings(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate([
            'mock_exam_passing_percentage' => 'required|integer|min:1|max:100',
            'mock_exam_maximum_attempts' => 'nullable|integer|in:1,2,3,4,5',
            'timing_mode' => 'nullable|in:unlimited,timed',
            'time_limit_minutes' => 'nullable|required_if:timing_mode,timed|integer|min:1|max:1440',
            'question_count' => 'required|integer|min:1|max:5000',
        ]);
        $course->update([
            'mock_exam_passing_percentage' => $data['mock_exam_passing_percentage'],
            'mock_exam_maximum_attempts' => $data['mock_exam_maximum_attempts'] ?? null,
            'mock_exam_time_limit_minutes' => array_key_exists('timing_mode', $data)
                ? ($data['timing_mode'] === 'timed' ? $data['time_limit_minutes'] : null)
                : $course->mock_exam_time_limit_minutes,
            'mock_exam_question_count' => $data['question_count'],
        ]);
        AuditLog::create([
            'user_id'=>Auth::id(), 'action'=>'Mock Exam Settings Updated',
            'description'=>"Updated {$course->title} Mock Exam passing score to {$data['mock_exam_passing_percentage']}% and maximum attempts to " . ($data['mock_exam_maximum_attempts'] ?? 'Unlimited') . '.',
            'ip_address'=>$request->ip(),
        ]);
        return back()->with('success', 'Mock Exam settings updated successfully.');
    }

    public function updateAssessmentPassRule(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate([
            'assessment_scope' => 'required|in:topic,subtopic',
            'assessment_id' => 'required|integer',
            'passing_percentage' => 'required|integer|min:1|max:100',
            'timing_mode' => 'nullable|in:unlimited,timed',
            'time_limit_minutes' => 'nullable|required_if:timing_mode,timed|integer|min:1|max:1440',
            'question_count' => 'required|integer|min:1|max:5000',
        ]);
        if ($data['assessment_scope'] === 'topic') {
            $assessment = Topic::where('course_id', $course->id)->findOrFail($data['assessment_id']);
            $assessment->update([
                'quiz_passing_percentage' => $data['passing_percentage'],
                'assessment_time_limit_minutes' => array_key_exists('timing_mode', $data)
                    ? ($data['timing_mode'] === 'timed' ? $data['time_limit_minutes'] : null)
                    : $assessment->assessment_time_limit_minutes,
                'assessment_question_count' => $data['question_count'],
            ]);
            $label = $assessment->title . ' Topic Quiz';
        } else {
            $assessment = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))->findOrFail($data['assessment_id']);
            abort_unless(in_array($assessment->content_type, ['pre_test','post_test','practice_test'], true), 422);
            $assessment->update([
                'passing_percentage' => $data['passing_percentage'],
                'assessment_time_limit_minutes' => array_key_exists('timing_mode', $data)
                    ? ($data['timing_mode'] === 'timed' ? $data['time_limit_minutes'] : null)
                    : $assessment->assessment_time_limit_minutes,
                'assessment_question_count' => $data['question_count'],
            ]);
            $label = $assessment->title;
        }
        AuditLog::create(['user_id'=>Auth::id(),'action'=>'Assessment Pass Rule Updated','description'=>"Updated {$label} passing score to {$data['passing_percentage']}% in {$course->title}.",'ip_address'=>$request->ip()]);
        return back()->with('success', 'Assessment passing percentage updated successfully.');
    }

    // ─── COURSE CRUD ──────────────────────────────────────────────
    public function storeCourse(Request $request)
    {
        $isAdmin = Auth::user()->hasAdminAccess();
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|string',
        ]);

        $course = Course::create([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'thumbnail_url' => $request->input('thumbnail_url'),
            'is_published' => $isAdmin,
            'approval_status' => $isAdmin ? 'approved' : 'pending',
            'created_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Create Course',
            'description' => 'Created course: ' . $course->title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Course created successfully.');
    }

    public function updateCourse(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $isAdmin = Auth::user()->hasAdminAccess();
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|string',
            'approval_status' => $isAdmin ? 'required|in:pending,approved,rejected' : 'nullable',
        ]);

        $updates = [
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'thumbnail_url' => $request->input('thumbnail_url'),
        ];
        if ($isAdmin) {
            $updates += [
                'approval_status' => $request->input('approval_status'),
                'is_published' => $request->input('approval_status') === 'approved',
            ];
        }
        DB::transaction(function () use ($course, $updates, $isAdmin, $request) {
            $course->update($updates);
            if (! $isAdmin || ! in_array($request->input('approval_status'), ['approved', 'pending'], true)) return;
            $status = $request->input('approval_status');
            Subject::where('course_id', $course->id)->update(['status' => $status]);
            Topic::where('course_id', $course->id)->update(['status' => $status]);
            Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))->update(['status' => $status]);
            QuizQuestion::where('course_id', $course->id)->update(['status' => $status]);
        });

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Update Course',
            'description' => 'Updated course: ' . $course->title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Course updated successfully.');
    }

    public function destroyCourse(Request $request, $id)
    {
        abort_if($request->user()->isAssistantAdmin(), 403);
        $course = Course::findOrFail($id);
        $title = $course->title;
        $course->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete Course',
            'description' => 'Deleted course: ' . $title,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Course deleted successfully.');
    }

    public function contentTopics(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        if (! $request->filled('subject_id')) {
            return redirect()->route('admin.content.subjects', $course->id)
                ->with('success', 'Select Manage beside a subject to open its content.');
        }
        $managedSubject = Subject::where('course_id', $course->id)->findOrFail($request->integer('subject_id'));
        $topics = Topic::with(['subtopics', 'course', 'subject'])
                    ->where('course_id', $course->id)
                    ->where('subject_id', $managedSubject->id)
                    ->orderBy('sort_order')
                    ->get();
        $subjects = collect([$managedSubject]);
        $importableTopics = Topic::with('subject')
            ->where('course_id', $course->id)
            ->where('subject_id', '!=', $managedSubject->id)
            ->whereNotNull('subject_id')
            ->orderBy('subject_id')->orderBy('sort_order')->orderBy('title')->get();
        return view('admin.content.topics', compact('topics', 'subjects', 'course', 'managedSubject', 'importableTopics'));
    }

    public function contentSubjects(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $subjects = Subject::where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('admin.content.subjects', compact('subjects', 'course'));
    }

    public function reorderSubjects(Request $request, $course_id)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);
        $course = Course::findOrFail($course_id);
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        DB::transaction(function () use ($data, $course, $request) {
            $current = $course->subjects()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $requested = collect($data['ordered_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
            abort_unless($current === $requested, 422, 'The subject list has changed. Reload the page and try again.');
            foreach ($data['ordered_ids'] as $index => $id) {
                $course->subjects()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
            AuditLog::create([
                'user_id' => Auth::id(), 'action' => 'Reorder Subjects',
                'description' => 'Reordered subjects for ' . $course->title,
                'ip_address' => $request->ip(),
            ]);
        });
        return response()->json(['success' => true, 'message' => 'Subject order saved.']);
    }

    public function storeSubject(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $data = $this->validateSubject($request, $course->id);

        $subject = DB::transaction(function () use ($course, $data) {
            $subject = $course->subjects()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? ($course->subjects()->max('sort_order') + 1),
                'status' => (Auth::user()->hasAdminAccess()) ? 'approved' : 'pending',
            ]);
            app(\App\Services\SubjectPolicy::class)->ensureDefault($subject);
            return $subject;
        });

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create Subject',
            'description' => 'Created subject: ' . $subject->title . ' for ' . $course->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Subject created successfully.');
    }

    private function validateSubject(Request $request, int $courseId, ?int $subjectId = null): array
    {
        $data = $request->validate([
            'subject_code' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('subjects')->where('course_id', $courseId)->ignore($subjectId)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'is_intensive_final_coaching' => 'nullable|boolean',
            'is_mentoring' => 'nullable|boolean',
        ]);
        $data['is_intensive_final_coaching'] = $request->boolean('is_intensive_final_coaching');
        $data['is_mentoring'] = $request->boolean('is_mentoring');
        return $data;
    }

    public function updateSubject(Request $request, $course_id, $subject_id)
    {
        $course = Course::findOrFail($course_id);
        $subject = Subject::where('course_id', $course->id)->findOrFail($subject_id);
        $data = $this->validateSubject($request, $course->id, $subject->id);

        $subject->update($data);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update Subject',
            'description' => 'Updated subject: ' . $subject->title . ' for ' . $course->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Subject updated successfully.');
    }

    public function destroySubject(Request $request, $course_id, $subject_id)
    {
        $course = Course::findOrFail($course_id);
        $subject = Subject::where('course_id', $course->id)->findOrFail($subject_id);
        $title = $subject->title;
        $subject->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete Subject',
            'description' => 'Deleted subject: ' . $title . ' from ' . $course->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Subject deleted successfully.');
    }

    public function updateSubjectStatus(Request $request, $course_id, $subject_id)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);
        $data = $request->validate(['status' => ['required', 'in:approved,pending']]);
        $subject = Subject::where('course_id', $course_id)->findOrFail($subject_id);
        $subject->update(['status' => $data['status']]);
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update Subject Status',
            'description' => 'Set subject ' . $subject->title . ' to ' . $data['status'],
            'ip_address' => $request->ip(),
        ]);
        $message = $data['status'] === 'approved'
            ? 'Subject approved and visible to eligible learners.'
            : 'Subject set to pending and hidden from learners.';
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => $subject->status, 'message' => $message]);
        }
        return back()->with('success', $message);
    }

    public function approveSubject(Request $request, $course_id, $subject_id)
    {
        if (!Auth::user()->hasAdminAccess()) abort(403);
        $subject = Subject::where('course_id', $course_id)->findOrFail($subject_id);
        $subject->update(['status' => 'approved']);
        return back()->with('success', 'Subject approved successfully.');
    }

    public function contentQuizzes(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        if (! $request->filled('subject_id')) {
            return redirect()->route('admin.content.subjects', $course->id)
                ->with('success', 'Select Manage beside a subject to open its assessments.');
        }
        $managedSubject = Subject::where('course_id', $course->id)->findOrFail($request->integer('subject_id'));
        $query = QuizQuestion::with(['topic', 'subtopic'])
            ->where('course_id', $course->id)
            ->whereHas('topic', fn ($query) => $query->where('subject_id', $managedSubject->id));
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('question', 'like', "%{$search}%");
        }
        
        $quizzes = $query->latest('id')->paginate(10)->withQueryString();
        $topics = Topic::where('course_id', $course->id)
            ->where('subject_id', $managedSubject->id)
            ->withCount(['quizQuestions as approved_quiz_questions_count' => fn ($query) => $query->where('question_type','quiz')->where('status','approved')])
            ->orderBy('sort_order')->get();
        $subjects = collect([$managedSubject]);
        $assessmentItems = Subtopic::whereHas('topic', fn ($query) => $query
                ->where('course_id', $course->id)->where('subject_id', $managedSubject->id))
            ->whereIn('content_type', ['pre_test', 'post_test', 'practice_test'])->with('topic.subject')->orderBy('sort_order')->get();
        $assessmentCounts = QuizQuestion::where('course_id', $course->id)
            ->whereHas('topic', fn ($query) => $query->where('subject_id', $managedSubject->id))
            ->selectRaw('question_type, count(*) as total')
            ->groupBy('question_type')
            ->pluck('total', 'question_type');
        $topicSetQuery = DB::table('topics')
            ->leftJoin('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->where('topics.course_id', $course->id)
            ->where('topics.subject_id', $managedSubject->id)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('quiz_questions')
                ->whereColumn('quiz_questions.topic_id', 'topics.id')
                ->where('quiz_questions.question_type', 'quiz')
                ->where('quiz_questions.status', 'approved'))
            ->select([
                DB::raw("'topic' as scope"), 'topics.id', 'topics.title',
                DB::raw("'quiz' as content_type"), 'subjects.subject_code',
                DB::raw('NULL as topic_title'),
                DB::raw('COALESCE(topics.quiz_passing_percentage, 80) as passing_percentage'),
                'topics.assessment_time_limit_minutes as time_limit_minutes',
                'topics.assessment_question_count as question_limit',
                DB::raw('NULL as maximum_attempts'), DB::raw('1 as sort_group'),
                DB::raw('topics.id as sort_value'),
            ])->selectSub(QuizQuestion::selectRaw('COUNT(*)')->whereColumn('topic_id', 'topics.id')
                ->where('question_type', 'quiz')->where('status', 'approved'), 'question_count');

        $subtopicSetQuery = DB::table('subtopics')
            ->join('topics', 'topics.id', '=', 'subtopics.topic_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->where('topics.course_id', $course->id)
            ->where('topics.subject_id', $managedSubject->id)
            ->whereIn('subtopics.content_type', ['pre_test', 'post_test', 'practice_test'])
            ->select([
                DB::raw("'subtopic' as scope"), 'subtopics.id', 'subtopics.title',
                'subtopics.content_type', 'subjects.subject_code', 'topics.title as topic_title',
                DB::raw('COALESCE(subtopics.passing_percentage, 80) as passing_percentage'),
                'subtopics.assessment_time_limit_minutes as time_limit_minutes',
                'subtopics.assessment_question_count as question_limit',
                'subtopics.maximum_attempts', DB::raw('2 as sort_group'),
                DB::raw('subtopics.id as sort_value'),
            ])->selectSub(QuizQuestion::selectRaw('COUNT(*)')->whereColumn('subtopic_id', 'subtopics.id'), 'question_count');

        $assessmentSetQuery = DB::query()->fromSub(
            $topicSetQuery->unionAll($subtopicSetQuery),
            'assessment_sets'
        );
        if ($request->filled('sets_search')) {
            $setsSearch = trim((string) $request->input('sets_search'));
            $assessmentSetQuery->where(function ($query) use ($setsSearch) {
                $query->where('title', 'like', "%{$setsSearch}%")
                    ->orWhere('topic_title', 'like', "%{$setsSearch}%")
                    ->orWhere('subject_code', 'like', "%{$setsSearch}%")
                    ->orWhere('content_type', 'like', "%{$setsSearch}%");
            });
        }
        $assessmentSets = $assessmentSetQuery->orderBy('sort_group')->orderBy('sort_value')
            ->paginate(6, ['*'], 'sets_page')->withQueryString();
        $pendingQuestionsCount = QuizQuestion::where('course_id', $course->id)
            ->whereHas('topic', fn ($query) => $query->where('subject_id', $managedSubject->id))
            ->where('status', 'pending')->count();
        return view('admin.content.quizzes', compact('quizzes', 'topics', 'subjects', 'assessmentItems', 'course', 'assessmentCounts', 'assessmentSets', 'pendingQuestionsCount', 'managedSubject'));
    }

    // ─── TOPIC CRUD ──────────────────────────────────────────────
    public function reorderTopics(Request $request, $course_id)
    {
        $request->validate([
            'subject_id' => ['required', \Illuminate\Validation\Rule::exists('subjects', 'id')->where('course_id', $course_id)],
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:topics,id'
        ]);

        $validTopicCount = Topic::where('course_id', $course_id)
            ->where('subject_id', $request->integer('subject_id'))
            ->whereIn('id', $request->ordered_ids)
            ->count();
        abort_unless($validTopicCount === count($request->ordered_ids), 422, 'Every topic must belong to the managed subject.');

        foreach ($request->ordered_ids as $index => $id) {
            Topic::where('course_id', $course_id)
                ->where('subject_id', $request->integer('subject_id'))
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function importTopic(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $data = $request->validate([
            'subject_id' => ['required', \Illuminate\Validation\Rule::exists('subjects', 'id')->where('course_id', $course->id)],
            'source_topic_id' => ['required', \Illuminate\Validation\Rule::exists('topics', 'id')->where('course_id', $course->id)],
        ]);

        $targetSubject = Subject::where('course_id', $course->id)->findOrFail($data['subject_id']);
        $source = Topic::with(['subtopics.questions', 'quizQuestions'])->where('course_id', $course->id)->findOrFail($data['source_topic_id']);
        if ((int) $source->subject_id === (int) $targetSubject->id) {
            return back()->withErrors(['source_topic_id' => 'Choose a topic from another subject.']);
        }

        $status = (Auth::user()->hasAdminAccess()) ? 'approved' : 'pending';
        $newTopic = DB::transaction(function () use ($source, $targetSubject, $course, $status) {
            $topicCopy = $source->replicate();
            $topicCopy->course_id = $course->id;
            $topicCopy->subject_id = $targetSubject->id;
            $topicCopy->sort_order = (Topic::where('course_id', $course->id)->where('subject_id', $targetSubject->id)->max('sort_order') ?? 0) + 1;
            $topicCopy->status = $status;
            $topicCopy->save();

            $subtopicMap = [];
            foreach ($source->subtopics as $subtopic) {
                $copy = $subtopic->replicate();
                $copy->topic_id = $topicCopy->id;
                $copy->status = $status;
                if ($subtopic->documentation_path) {
                    $path = $this->copyImportedPublicFile($subtopic->documentation_path, 'documentation/imported');
                    $copy->documentation_path = $path ? '/storage/' . $path : $subtopic->documentation_path;
                }
                if ($subtopic->video_path) {
                    $copy->video_path = $this->copyImportedPublicFile($subtopic->video_path, 'subtopic-videos/imported') ?: $subtopic->video_path;
                }
                $copy->save();
                $subtopicMap[$subtopic->id] = $copy->id;
            }

            foreach ($source->quizQuestions as $question) {
                $copy = $question->replicate();
                $copy->course_id = $course->id;
                $copy->topic_id = $topicCopy->id;
                $copy->subtopic_id = $question->subtopic_id ? ($subtopicMap[$question->subtopic_id] ?? null) : null;
                $copy->status = $status;
                if ($question->image_path) {
                    $copy->image_path = $this->copyImportedPublicFile($question->image_path, 'question-images/imported') ?: $question->image_path;
                }
                $copy->save();
            }

            return $topicCopy;
        });

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Import Topic',
            'description' => 'Imported topic ' . $source->title . ' into subject ' . $targetSubject->subject_code,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Topic and its content were imported into ' . $targetSubject->title . '.');
    }

    private function copyImportedPublicFile(string $storedPath, string $directory): ?string
    {
        $sourcePath = ltrim(str_replace('/storage/', '', $storedPath), '/');
        if (! Storage::disk('public')->exists($sourcePath)) return null;
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $targetPath = trim($directory, '/') . '/' . uniqid('import_', true) . ($extension ? '.' . $extension : '');
        return Storage::disk('public')->copy($sourcePath, $targetPath) ? $targetPath : null;
    }



    public function storeTopic(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $request->validate([
            'subject_id' => ['required', \Illuminate\Validation\Rule::exists('subjects', 'id')->where('course_id', $course->id)],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $status       = (Auth::user()->hasAdminAccess()) ? 'approved' : 'pending';
        $maxSortOrder = Topic::where('course_id', $course->id)->max('sort_order') ?? 0;

        $topic = Topic::create([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'course_id'   => $course->id,
            'subject_id'  => $request->integer('subject_id'),
            'sort_order'  => $maxSortOrder + 1,
            'status'      => $status,
        ]);

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Create Topic',
            'description' => 'Created topic: ' . $topic->title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Topic created successfully.');
    }

    public function updateTopic(Request $request, $course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $topic = Topic::where('course_id', $course->id)->findOrFail($id);
        $request->validate([
            'subject_id' => ['required', \Illuminate\Validation\Rule::exists('subjects', 'id')->where('course_id', $course->id)],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $topic->update([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'subject_id'  => $request->integer('subject_id'),
        ]);

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Update Topic',
            'description' => 'Updated topic: ' . $topic->title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Topic updated successfully.');
    }

    public function destroyTopic(Request $request, $course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $topic = Topic::where('course_id', $course->id)->findOrFail($id);
        $title = $topic->title;
        $topic->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete Topic',
            'description' => 'Deleted topic: ' . $title,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Topic deleted successfully.');
    }


    // ─── SUBTOPIC CRUD ───────────────────────────────────────────
    private function normalizeSubtopicFormat(Request $request, ?Subtopic $existing = null): ?string
    {
        $format = $request->input('content_type');
        if (!in_array($format, ['text', 'video', 'reading'], true)) return null;
        $hasVideo = $existing && (filled($existing->video_url) || filled($existing->video_path));
        $hasDocument = $existing && filled($existing->documentation_path);
        $request->validate(match ($format) {
            'text' => ['text_body' => 'required|string|max:15000', 'video_url' => 'prohibited', 'video_file' => 'prohibited', 'documentation' => 'prohibited'],
            'video' => ['documentation' => 'prohibited'],
            'reading' => ['documentation' => 'nullable|file|mimes:pdf|max:51200', 'video_url' => 'prohibited', 'video_file' => 'prohibited'],
        });
        $error = match ($format) {
            'text' => $hasVideo || $hasDocument ? 'Create a new Text Content item to keep the existing media intact.' : null,
            'video' => $hasDocument ? 'Create a new video item to keep the existing handout intact.' : ((!$request->filled('video_url') && !$request->hasFile('video_file') && (!$existing?->video_path || $request->boolean('remove_video_file'))) ? 'Provide a video link or upload a video file.' : null),
            'reading' => $hasVideo ? 'Create a new reading item to keep the existing video intact.' : ((!$request->hasFile('documentation') && (!$hasDocument || $request->boolean('remove_documentation') || !preg_match('/\.pdf(?:$|[?#])/i', $existing->documentation_filename ?: $existing->documentation_path))) ? 'Upload a PDF handout.' : null),
        };
        if ($error) throw \Illuminate\Validation\ValidationException::withMessages(['content_type' => $error]);
        $request->merge(['content_type' => 'subtopic']);
        return $format;
    }

    public function storeSubtopic(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $format = $this->normalizeSubtopicFormat($request);
        $request->validate([
            'topic_id'    => ['required', \Illuminate\Validation\Rule::exists('topics', 'id')->where('course_id', $course->id)],
            'content_type' => 'required|in:subtopic,pre_test,post_test,practice_test,mock_exam',
            'title'       => 'required_if:content_type,subtopic|nullable|string|max:255',
            'instructions' => 'required_unless:content_type,subtopic|nullable|string|max:5000',
            'maximum_attempts' => 'nullable|in:1,2,3,4,5',
            'video_url'   => 'nullable|string|max:500',
            'video_file'  => 'nullable|file|mimes:mp4,webm,mov,m4v|max:38912',
            'documentation' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png,gif,webp|max:51200',
            'sort_order'  => 'nullable|integer|min:1',
            'zoom_url' => 'required_if:content_type,zoom_link|nullable|url|max:1000',
            'zoom_description' => 'required_if:content_type,zoom_link|nullable|string|max:5000',
            'zoom_starts_at' => 'required_if:content_type,zoom_link|nullable|date',
            'zoom_ends_at' => 'nullable|date|after:zoom_starts_at',
        ]);

        $status = (Auth::user()->hasAdminAccess()) ? 'approved' : 'pending';
        $maxOrder = Subtopic::where('topic_id', $request->input('topic_id'))->max('sort_order') ?? 0;

        $contentType = $request->input('content_type');
        $assessmentTitles = ['pre_test' => 'Pre-test', 'post_test' => 'Post-test', 'practice_test' => 'Practice Test', 'mock_exam' => 'Mock Exam'];
        $data = [
            'topic_id'   => $request->input('topic_id'),
            'content_type' => $contentType,
            'title'      => in_array($contentType, ['subtopic', 'zoom_link'], true) ? $request->input('title') : $assessmentTitles[$contentType],
            'instructions' => $format === 'text' ? $request->input('text_body') : ($contentType === 'subtopic' ? null : $request->input('instructions')),
            'maximum_attempts' => $contentType === 'pre_test' ? 1 : (in_array($contentType, ['post_test', 'practice_test', 'mock_exam'], true) ? $request->input('maximum_attempts') : null),
            'video_url'  => $contentType === 'subtopic' ? $request->input('video_url') : null,
            'sort_order' => $request->filled('sort_order') ? $request->integer('sort_order') : $maxOrder + 1,
            'status'     => $status,
            'zoom_url' => $contentType === 'zoom_link' ? $request->input('zoom_url') : null,
            'zoom_description' => $contentType === 'zoom_link' ? $request->input('zoom_description') : null,
            'zoom_starts_at' => $contentType === 'zoom_link' ? $request->input('zoom_starts_at') : null,
            'zoom_ends_at' => $contentType === 'zoom_link' ? $request->input('zoom_ends_at') : null,
        ];

        if ($contentType === 'subtopic' && $request->hasFile('documentation')) {
            [$path, $originalName] = $this->storeDocumentationUpload($request->file('documentation'));
            $data['documentation_path']     = '/storage/' . $path;
            $data['documentation_filename'] = $originalName;
        }
        if ($contentType === 'subtopic' && $request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $data['video_path'] = $file->store('subtopic-videos', 'public');
            $data['video_filename'] = $file->getClientOriginalName();
            $data['video_url'] = null;
        }

        $sub = Subtopic::create($data);

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Create Subtopic',
            'description' => 'Created subtopic: ' . $sub->title . ' (Topic ID ' . $sub->topic_id . ')',
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Subtopic created successfully.');
    }

    public function updateSubtopic(Request $request, $course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $sub = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))->findOrFail($id);
        $format = $this->normalizeSubtopicFormat($request, $sub);
        $request->validate([
            'content_type' => 'required|in:subtopic,zoom_link,pre_test,post_test,practice_test,mock_exam',
            'title'      => 'required_if:content_type,subtopic,zoom_link|nullable|string|max:255',
            'instructions' => 'required_unless:content_type,subtopic,zoom_link|nullable|string|max:5000',
            'maximum_attempts' => 'nullable|in:1,2,3,4,5',
            'video_url'  => 'nullable|string|max:500',
            'video_file' => 'nullable|file|mimes:mp4,webm,mov,m4v|max:38912',
            'documentation' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png,gif,webp|max:51200',
            'remove_video_file' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1',
            'zoom_url' => 'required_if:content_type,zoom_link|nullable|url|max:1000',
            'zoom_description' => 'required_if:content_type,zoom_link|nullable|string|max:5000',
            'zoom_starts_at' => 'required_if:content_type,zoom_link|nullable|date',
            'zoom_ends_at' => 'nullable|date|after:zoom_starts_at',
        ]);

        $contentType = $request->input('content_type');
        $assessmentTitles = ['pre_test' => 'Pre-test', 'post_test' => 'Post-test', 'practice_test' => 'Practice Test', 'mock_exam' => 'Mock Exam'];
        $data = [
            'content_type' => $contentType,
            'title'      => in_array($contentType, ['subtopic', 'zoom_link'], true) ? $request->input('title') : $assessmentTitles[$contentType],
            'instructions' => $format === 'text' ? $request->input('text_body') : ($contentType === 'subtopic' ? null : $request->input('instructions')),
            'maximum_attempts' => $contentType === 'pre_test' ? 1 : (in_array($contentType, ['post_test', 'practice_test', 'mock_exam'], true) ? $request->input('maximum_attempts') : null),
            'video_url'  => $contentType === 'subtopic' ? $request->input('video_url') : null,
            'sort_order' => $request->filled('sort_order') ? $request->integer('sort_order') : $sub->sort_order,
            'zoom_url' => $contentType === 'zoom_link' ? $request->input('zoom_url') : null,
            'zoom_description' => $contentType === 'zoom_link' ? $request->input('zoom_description') : null,
            'zoom_starts_at' => $contentType === 'zoom_link' ? $request->input('zoom_starts_at') : null,
            'zoom_ends_at' => $contentType === 'zoom_link' ? $request->input('zoom_ends_at') : null,
        ];

        if ($contentType !== 'subtopic') {
            $data['documentation_path'] = null;
            $data['documentation_filename'] = null;
        } elseif ($request->hasFile('documentation')) {
            [$path, $originalName] = $this->storeDocumentationUpload($request->file('documentation'));
            $data['documentation_path']     = '/storage/' . $path;
            $data['documentation_filename'] = $originalName;
        } elseif ($request->input('remove_documentation') === '1') {
            $data['documentation_path']     = null;
            $data['documentation_filename'] = null;
        }
        if ($contentType !== 'subtopic' || $request->boolean('remove_video_file')) {
            if ($sub->video_path) Storage::disk('public')->delete($sub->video_path);
            $data['video_path'] = null;
            $data['video_filename'] = null;
        }
        if ($contentType === 'subtopic' && $request->hasFile('video_file')) {
            if ($sub->video_path) Storage::disk('public')->delete($sub->video_path);
            $file = $request->file('video_file');
            $data['video_path'] = $file->store('subtopic-videos', 'public');
            $data['video_filename'] = $file->getClientOriginalName();
            $data['video_url'] = null;
        } elseif ($contentType === 'subtopic' && $request->filled('video_url') && $sub->video_path) {
            Storage::disk('public')->delete($sub->video_path);
            $data['video_path'] = null;
            $data['video_filename'] = null;
        }

        $sub->update($data);

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Update Subtopic',
            'description' => 'Updated subtopic: ' . $sub->title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Subtopic updated successfully.');
    }

    private function storeDocumentationUpload(\Illuminate\Http\UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'document.pdf';
        $path = $file->storeAs('documentation', now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName, 'public');

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'documentation' => 'The document could not be saved. Check the server upload directory and permissions.',
            ]);
        }

        return [$path, $originalName];
    }

    public function destroySubtopic(Request $request, $course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $sub = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))
            ->findOrFail($id);
        $title = $sub->title;
        if ($sub->video_path) Storage::disk('public')->delete($sub->video_path);
        if ($sub->documentation_path) Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $sub->documentation_path), '/'));
        $sub->delete();

        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Delete Subtopic',
            'description' => 'Deleted subtopic: ' . $title,
            'ip_address'  => $request->ip()
        ]);

        return back()->with('success', 'Subtopic deleted successfully.');
    }

    public function approveSubtopic(Request $request, $course_id, $id)
    {
        if (!Auth::user()->hasAdminAccess()) {
            abort(403, 'Unauthorized action.');
        }
        $sub = Subtopic::findOrFail($id);
        $sub->update(['status' => 'approved']);
        return back()->with('success', 'Subtopic approved successfully.');
    }


    // ─── QUIZ CRUD ───────────────────────────────────────────────
    private function validatedQuestionData(Request $request, int $courseId): array
    {
        $data = $request->validate([
            'question_type' => 'required|in:quiz,midterm,final,subtopic_assessment,pre_test,post_test',
            'response_type' => 'required|in:single,sata,grid,cloze,highlight',
            'category' => 'nullable|in:general,easy,average,difficult',
            'topic_id' => 'nullable|integer|exists:topics,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'subtopic_id' => 'nullable|integer|exists:subtopics,id',
            'question' => 'required|string|max:2000',
            'rationale' => 'nullable|string|max:5000',
            'options' => 'nullable|array|max:8',
            'options.*' => 'nullable|string|max:255',
            'answer' => 'nullable|integer|min:0',
            'correct_answers' => 'nullable|array|min:1',
            'correct_answers.*' => 'integer|min:0',
            'question_image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'remove_question_image' => 'nullable|boolean',
            'response_config' => 'required_if:response_type,grid,cloze,highlight|nullable|json',
            'maximum_points' => 'nullable|numeric|min:0.01|max:9999',
            'scoring_method' => 'nullable|in:all_or_nothing,partial_credit',
        ]);
        $data['category'] = $data['category'] ?? 'average';

        if ($data['question_type'] === 'quiz') {
            $topic = Topic::where('course_id', $courseId)->find($data['topic_id'] ?? null);
            if (!$topic) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'topic_id' => 'A topic from this course is required for a topic quiz.',
                ]);
            }
            $data['subtopic_id'] = null;
        } elseif (in_array($data['question_type'], ['subtopic_assessment', 'pre_test', 'post_test'], true)) {
            $subject = Subject::where('course_id', $courseId)->find($data['subject_id'] ?? null);
            if (!$subject) throw \Illuminate\Validation\ValidationException::withMessages(['subject_id' => 'Select a subject from this course.']);
            $item = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $courseId))
                ->where('id', $data['subtopic_id'] ?? null)->where('content_type', '!=', 'subtopic')->first();
            if (!$item) throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id' => 'Select an assessment entry from this course.']);
            if ((int) $item->topic->subject_id !== (int) $subject->id) throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id' => 'Select an assessment entry from the chosen subject.']);
            if (in_array($data['question_type'], ['pre_test', 'post_test'], true) && $item->content_type !== $data['question_type']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id' => 'Select a matching ' . str_replace('_', '-', $data['question_type']) . ' entry.']);
            }
            if ($data['question_type'] === 'subtopic_assessment' && $item->content_type !== 'practice_test') {
                throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id' => 'Select a Practice Test entry from the chosen subject.']);
            }
            $data['topic_id'] = $item->topic_id;
        } else {
            $data['topic_id'] = null;
            $data['subtopic_id'] = null;
        }

        unset($data['subject_id']);

        if (in_array($data['response_type'], ['grid', 'cloze', 'highlight'], true)) {
            $config = json_decode($data['response_config'], true);
            $validGrid = $data['response_type'] === 'grid' && is_array($config) && ($config['type'] ?? null) === 'dynamic_matrix_grid' && !empty($config['columns']) && !empty($config['rows']);
            $validCloze = $data['response_type'] === 'cloze' && is_array($config) && ($config['type'] ?? null) === 'cloze_dropdown' && !empty($config['blanks']);
            $validHighlight = $data['response_type'] === 'highlight' && is_array($config) && ($config['type'] ?? null) === 'highlight_text' && count($config['segments'] ?? []) >= 2 && collect($config['segments'] ?? [])->contains('is_correct', true);
            if (! $validGrid && ! $validCloze && ! $validHighlight) {
                $message = match ($data['response_type']) {
                    'grid' => 'Build and preview a valid matrix grid before saving.',
                    'cloze' => 'Configure every Cloze dropdown before saving.',
                    default => 'Add at least two highlightable phrases and mark at least one correct phrase.',
                };
                throw \Illuminate\Validation\ValidationException::withMessages(['response_config' => $message]);
            }
            if ($validCloze) {
                foreach ($config['blanks'] as $blank) {
                    if (empty($blank['key']) || count($blank['options'] ?? []) < 2 || collect($blank['options'] ?? [])->where('is_correct', true)->count() !== 1) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['response_config' => 'Every Cloze blank requires at least two choices and exactly one correct answer.']);
                    }
                    if (! str_contains($data['question'], '{{' . $blank['key'] . '}}')) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['question' => 'Every configured Cloze blank must appear in the question using {{blank_name}}.']);
                    }
                }
            }
            return [
                'topic_id' => $data['topic_id'] ?? null, 'subtopic_id' => $data['subtopic_id'] ?? null, 'course_id' => $courseId,
                'question_type' => $data['question_type'], 'response_type' => $data['response_type'], 'category' => $data['category'],
                'question' => $data['question'], 'rationale' => $data['rationale'] ?? null, 'options' => [], 'answer' => 0, 'correct_answers' => [],
                'response_config' => $config, 'maximum_points' => $data['maximum_points'] ?? ($config['maximum_points'] ?? 1),
                'scoring_method' => $data['scoring_method'] ?? 'all_or_nothing',
            ];
        }

        $data['options'] = collect($data['options'] ?? [])->map(fn ($option) => trim((string) $option))->filter()->values()->all();
        if (count($data['options']) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Add at least two non-empty answer choices.']);
        }

        $answers = $data['response_type'] === 'sata'
            ? ($data['correct_answers'] ?? [])
            : (isset($data['answer']) ? [(int) $data['answer']] : []);
        $answers = collect($answers)->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();

        if (empty($answers) || collect($answers)->contains(fn ($value) => !array_key_exists($value, $data['options']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct_answers' => 'Select at least one valid correct answer.',
            ]);
        }

        if ($data['response_type'] === 'sata' && count($answers) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct_answers' => 'SATA questions require at least two correct answers.',
            ]);
        }

        return [
            'topic_id' => $data['topic_id'] ?? null,
            'subtopic_id' => $data['subtopic_id'] ?? null,
            'course_id' => $courseId,
            'question_type' => $data['question_type'],
            'response_type' => $data['response_type'],
            'category' => $data['category'],
            'question' => $data['question'],
            'rationale' => $data['rationale'] ?? null,
            'options' => array_values($data['options']),
            'answer' => $answers[0],
            'correct_answers' => $answers,
            'response_config' => null,
            'maximum_points' => $data['maximum_points'] ?? 1,
            'scoring_method' => $data['scoring_method'] ?? 'all_or_nothing',
        ];
    }

    public function storeQuiz(Request $request, $course_id)
    {
        $status = (Auth::user()->hasAdminAccess()) ? 'approved' : 'pending';
        $questionData = $this->validatedQuestionData($request, (int) $course_id);
        if ($request->hasFile('question_image')) {
            $file = $request->file('question_image');
            $questionData['image_path'] = $file->store('question-images', 'public');
            $questionData['image_filename'] = $file->getClientOriginalName();
        }
        $quiz = QuizQuestion::create($questionData + [
            'status' => $status,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create Quiz Question',
            'description' => 'Created ' . $quiz->question_type . ' question: ' . substr($quiz->question, 0, 50),
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Question created successfully.');
    }

    public function bulkImportMultipleChoiceQuestions(Request $request, $course_id)
    {
        $course = Course::findOrFail($course_id);
        $data = $request->validate([
            'subject_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('subjects', 'id')->where('course_id', $course->id)],
            'question_type' => 'required|in:pre_test,post_test,subtopic_assessment',
            'subtopic_id' => 'nullable|integer|exists:subtopics,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $subject = Subject::where('course_id', $course->id)->findOrFail($data['subject_id']);
        $expectedType = $data['question_type'] === 'subtopic_assessment' ? 'practice_test' : $data['question_type'];
        $matchingEntries = Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id)->where('subject_id', $subject->id))
            ->where('content_type', $expectedType)->with('topic')->get();
        if ($matchingEntries->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id'=>'Create a matching assessment entry under this subject before importing questions.']);
        }
        $subtopic = $matchingEntries->count() === 1
            ? $matchingEntries->first()
            : $matchingEntries->firstWhere('id', (int) ($data['subtopic_id'] ?? 0));
        if (!$subtopic) throw \Illuminate\Validation\ValidationException::withMessages(['subtopic_id'=>'Select which matching assessment entry will receive these questions.']);
        $topic = $subtopic->topic;

        $handle = fopen($request->file('csv_file')->getRealPath(), 'rb');
        if (!$handle) throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'The CSV file could not be opened.']);
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'The CSV file is empty.']);
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value, "\xEF\xBB\xBF \t\n\r\0\x0B")), $header);
        $required = ['question','option_a','option_b','correct_answer'];
        $missing = array_values(array_diff($required, $header));
        if ($missing) {
            fclose($handle);
            throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'Missing required columns: '.implode(', ', $missing).'. Please use the Artemis CSV template.']);
        }

        $rows = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            if (count($rows) >= 5000) {
                fclose($handle);
                throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'A single upload may contain no more than 5,000 questions.']);
            }
            $values = array_pad($values, count($header), null);
            $row = array_combine($header, array_slice($values, 0, count($header)));
            $question = trim((string) ($row['question'] ?? ''));
            $options = [];
            foreach (range('a', 'h') as $letter) {
                $option = trim((string) ($row['option_'.$letter] ?? ''));
                if ($option !== '') $options[] = $option;
            }
            $correctLetter = strtoupper(trim((string) ($row['correct_answer'] ?? '')));
            $correctIndex = ord($correctLetter ?: '@') - ord('A');
            $category = strtolower(trim((string) ($row['category'] ?? 'average'))) ?: 'average';
            $points = trim((string) ($row['maximum_points'] ?? '1')) ?: '1';

            $errors = [];
            if ($question === '' || mb_strlen($question) > 2000) $errors[] = 'question is required and must not exceed 2,000 characters';
            if (count($options) < 2) $errors[] = 'at least option_a and option_b are required';
            if (collect($options)->contains(fn ($option) => mb_strlen($option) > 255)) $errors[] = 'each option must not exceed 255 characters';
            if ($correctIndex < 0 || $correctIndex >= count($options)) $errors[] = 'correct_answer must be the letter of a populated option';
            if (!in_array($category, ['general','easy','average','difficult'], true)) $errors[] = 'category must be general, easy, average, or difficult';
            if (!is_numeric($points) || (float) $points < 0.01 || (float) $points > 9999) $errors[] = 'maximum_points must be between 0.01 and 9999';
            if (mb_strlen((string) ($row['rationale'] ?? '')) > 5000) $errors[] = 'rationale must not exceed 5,000 characters';
            if ($errors) {
                fclose($handle);
                throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'CSV row '.$rowNumber.': '.implode('; ', $errors).'. No questions were imported.']);
            }
            $rows[] = [
                'course_id'=>$course->id, 'topic_id'=>$topic->id, 'subtopic_id'=>$subtopic?->id,
                'question_type'=>$data['question_type'], 'response_type'=>'single', 'category'=>$category,
                'question'=>$question, 'rationale'=>trim((string) ($row['rationale'] ?? '')) ?: null,
                'options'=>$options, 'answer'=>$correctIndex, 'correct_answers'=>[$correctIndex],
                'maximum_points'=>(float) $points, 'scoring_method'=>'all_or_nothing',
                'status'=>(Auth::user()->hasAdminAccess()) ? 'approved' : 'pending',
            ];
        }
        fclose($handle);
        if (!$rows) throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'The CSV file contains no question rows.']);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) QuizQuestion::create($row);
        });
        AuditLog::create(['user_id'=>Auth::id(), 'action'=>'Bulk Question Import', 'description'=>'Imported '.count($rows).' multiple-choice questions.', 'ip_address'=>$request->ip()]);

        return back()->with('success', count($rows).' multiple-choice questions imported successfully.');
    }

    public function updateQuiz(Request $request, $course_id, $id)
    {
        $quiz = QuizQuestion::where('course_id', $course_id)->findOrFail($id);
        $questionData = $this->validatedQuestionData($request, (int) $course_id);
        if ($request->hasFile('question_image')) {
            if ($quiz->image_path) Storage::disk('public')->delete($quiz->image_path);
            $file = $request->file('question_image');
            $questionData['image_path'] = $file->store('question-images', 'public');
            $questionData['image_filename'] = $file->getClientOriginalName();
        } elseif ($request->boolean('remove_question_image')) {
            if ($quiz->image_path) Storage::disk('public')->delete($quiz->image_path);
            $questionData['image_path'] = null;
            $questionData['image_filename'] = null;
        }
        $quiz->update($questionData);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update Quiz Question',
            'description' => 'Updated question ID: ' . $quiz->id,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Question updated successfully.');
    }

    public function destroyQuiz(Request $request, $course_id, $id)
    {
        $quiz = QuizQuestion::where('course_id', $course_id)->findOrFail($id);
        $desc = substr($quiz->question, 0, 50);
        if ($quiz->image_path) Storage::disk('public')->delete($quiz->image_path);
        $quiz->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete Quiz Question',
            'description' => 'Deleted question: ' . $desc,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Question deleted successfully.');
    }

    // ─── APPROVAL METHODS ────────────────────────────────────────
    public function approveTopic($course_id, $id)
    {
        if (!Auth::user()->hasAdminAccess()) {
            abort(403, 'Unauthorized action.');
        }
        $topic = Topic::where('course_id', $course_id)->findOrFail($id);
        $topic->update(['status' => 'approved']);
        return back()->with('success', 'Topic approved successfully.');
    }



    public function approveQuiz($course_id, $id)
    {
        if (!Auth::user()->hasAdminAccess()) {
            abort(403, 'Unauthorized action.');
        }
        $quiz = QuizQuestion::where('course_id', $course_id)->findOrFail($id);
        $quiz->update(['status' => 'approved']);
        return back()->with('success', 'Question approved successfully.');
    }

    public function approveAllQuizzes(Request $request, $course_id)
    {
        if (!Auth::user()->hasAdminAccess()) {
            abort(403, 'Unauthorized action.');
        }

        $course = Course::findOrFail($course_id);
        $subject = Subject::where('course_id', $course->id)->findOrFail($request->integer('subject_id'));
        $approvedCount = QuizQuestion::where('course_id', $course->id)
            ->whereHas('topic', fn ($query) => $query->where('subject_id', $subject->id))
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        $message = $approvedCount === 1
            ? '1 pending question was approved successfully.'
            : "{$approvedCount} pending questions were approved successfully.";

        return back()->with('success', $message);
    }

    // ─── PROGRESS OVERVIEW ───────────────────────────────────────
    public function progress()
    {
        $attempts = QuizAttempt::with(['user', 'topic'])
            ->orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'attempts_page')
            ->withQueryString();
        return view('admin.progress.index', compact('attempts'));
    }

    // ─── VOUCHER MANAGEMENT ──────────────────────────────────────
    public function vouchers(Request $request)
    {
        $user = Auth::user();
        $paymentTableOnly = $request->ajax() && $request->header('X-Admin-Table') === 'vouchers-table';
        if ($user && !$paymentTableOnly) {
            $user->last_vouchers_viewed_at = now();
            $user->save();
        }
        
        $searchInput = $request->query('voucher_search', '');
        $voucherSearch = is_string($searchInput) ? mb_substr(trim($searchInput), 0, 120) : '';
        $voucherPageSize = $request->integer('voucher_size', 5);
        if (!in_array($voucherPageSize, [5, 10, 25, 50], true)) $voucherPageSize = 5;
        $vouchers = null;
        if ($paymentTableOnly || $request->hasAny(['voucher_search', 'voucher_size', 'vouchers_page'])) {
            $vouchers = Voucher::with(['user', 'assignedLearner', 'batch.courses'])
            ->when($voucherSearch !== '', function ($query) use ($voucherSearch) {
                $term = '%'.$voucherSearch.'%';
                $query->where(function ($query) use ($term) {
                    $learner = fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    $query->where('code', 'like', $term)->orWhereHas('assignedLearner', $learner)->orWhereHas('user', $learner)
                        ->orWhereHas('batch', fn ($batch) => $batch->where('name', 'like', $term)->orWhere('code', 'like', $term))
                        ->orWhereHas('batch.courses', fn ($course) => $course->where('title', 'like', $term));
                });
            })
            ->orderBy('created_at', 'desc')
            ->orderByDesc('id')->paginate($voucherPageSize, ['*'], 'vouchers_page')
            ->withQueryString();
        }
        if ($paymentTableOnly) {
            return view('admin.partials.payment-directory', compact('vouchers', 'voucherSearch', 'voucherPageSize'));
        }
        $paymentBatches = $user->hasAdminAccess() ? CourseBatch::available()->has('courses')->orderBy('name')->get(['id', 'name', 'code']) : collect();
        $automaticPayments = $user->hasAdminAccess() && app(\App\Services\AutomaticPayments::class)->enabled();
        return view('admin.vouchers.index', compact('vouchers', 'paymentBatches', 'automaticPayments', 'voucherSearch', 'voucherPageSize'));
    }

    public function generateVouchers(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100'
        ]);

        $quantity = $request->input('quantity');
        
        for ($i = 0; $i < $quantity; $i++) {
            $seg = function() {
                return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            };
            $code = "ART2-" . $seg() . "-" . $seg();

            Voucher::create([
                'code' => $code,
                'price' => 299.00,
                'used' => false
            ]);
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Generate Vouchers',
            'description' => 'Generated ' . $quantity . ' prepaid voucher codes.',
            'ip_address' => $request->ip()
        ]);

        return back();
    }

    // ─── CERTIFICATE RECORD ──────────────────────────────────────
    public function certificates()
    {
        $user = Auth::user();
        if ($user) {
            $user->last_certificates_viewed_at = now();
            $user->save();
        }
        
        $certificates = Certificate::with('user')
            ->orderBy('issued_at', 'desc')
            ->paginate(15, ['*'], 'certificates_page')
            ->withQueryString();
        $certificateStats = [
            'issued' => Certificate::count(),
            'learners' => User::where('is_admin', false)->count(),
        ];
        return view('admin.certificates.index', compact('certificates', 'certificateStats'));
    }

    // ─── REPORTS ─────────────────────────────────────────────────
    public function reports()
    {
        // Calculate some aggregate metrics
        $totalSales = Voucher::count();
        $totalRevenue = Voucher::sum('price');
        $passedExams = QuizAttempt::whereNull('topic_id')->where('passed', true)->count();
        $failedExams = QuizAttempt::whereNull('topic_id')->where('passed', false)->count();
        $totalExams = $passedExams + $failedExams;

        $completedStudents = Certificate::count();
        $startedStudents = User::where('is_admin', false)->count();

        return view('admin.reports.index', compact(
            'totalSales', 'totalRevenue', 'passedExams', 'failedExams', 'totalExams', 'completedStudents', 'startedStudents'
        ));
    }

    // ─── ANNOUNCEMENTS ───────────────────────────────────────────
    public function announcements()
    {
        $announcements = Announcement::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'announcements_page')
            ->withQueryString();
        return view('admin.notifications.index', compact('announcements'));
    }

    public function createAnnouncement(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        Announcement::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'created_by' => Auth::id()
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'Announcement Created',
            'description' => 'Created global notice: ' . $request->input('title'),
            'ip_address' => $request->ip()
        ]);

        return back();
    }

    // ─── AUDIT SECURITY LOGS ─────────────────────────────────────
    public function auditLogs()
    {
        $logs = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20, ['*'], 'audit_page')
            ->withQueryString();
        return view('admin.audit-logs.index', compact('logs'));
    }

    // ─── SETTINGS ────────────────────────────────────────────────
    public function settings()
    {
        $testBanks = \App\Models\TestBank::with('course:id,title')->orderBy('title')->get();
        return view('admin.settings.index', compact('testBanks'));
    }
}
