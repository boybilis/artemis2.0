<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\QuizQuestion;
use App\Models\TestBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TestBankController extends Controller
{
    public function catalog()
    {
        $user = Auth::user();
        $testBanks = TestBank::query()
            ->with('course:id,title')
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        return response()->json(['success' => true, 'testBanks' => $testBanks->map(fn (TestBank $testBank) => [
            'id' => $testBank->id,
            'title' => $testBank->title,
            'code' => $testBank->code,
            'description' => $testBank->description,
            'price' => (float) $testBank->price,
            'usdPrice' => $testBank->usd_price === null ? null : (float) $testBank->usd_price,
            'accessDays' => $testBank->access_days,
            'isSubscribed' => $user->testBankEnrollments()
                ->where('test_bank_id', $testBank->id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            'course' => ['id' => $testBank->course?->id, 'title' => $testBank->course?->title],
        ])->values()]);
    }

    public function buy(TestBank $testBank)
    {
        $user = Auth::user();
        abort_unless($testBank->status === 'active', 404);

        do {
            $reference = 'ART2TB-' . strtoupper(bin2hex(random_bytes(6)));
        } while (PaymentTransaction::where('reference', $reference)->exists());

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'test_bank_id' => $testBank->id,
            'reference' => $reference,
            'amount' => $testBank->price,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'status' => 'pending_payment',
        ]);

        $secretKey = config('services.paymongo.secret_key');
        if (!$secretKey) {
            $transaction->update(['status' => 'payment_configuration_error']);
            return response()->json(['success' => false, 'message' => 'Online payment is not configured yet.'], 503);
        }

        $description = 'Artemis 2.0 Test Bank: ' . $testBank->title;
        $response = Http::withBasicAuth($secretKey, '')->acceptJson()->post('https://api.paymongo.com/v1/checkout_sessions', [
            'data' => ['attributes' => [
                'billing' => array_filter(['name' => $user->name, 'email' => $user->email]),
                'cancel_url' => url('/?payment_cancelled=1'),
                'description' => $description,
                'line_items' => [[
                    'amount' => (int) round(((float) $testBank->price) * 100),
                    'currency' => 'PHP',
                    'description' => $description,
                    'name' => $testBank->title,
                    'quantity' => 1,
                ]],
                'payment_method_types' => config('services.paymongo.payment_methods', ['qrph']),
                'reference_number' => $reference,
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'success_url' => route('payments.paymongo.success', ['reference' => $reference]),
            ]],
        ]);

        if (!$response->successful()) {
            $transaction->update(['status' => 'payment_creation_failed']);
            Log::error('PayMongo Test Bank checkout failed.', ['transaction' => $transaction->id, 'response' => $response->json()]);
            return response()->json(['success' => false, 'message' => 'PayMongo could not create the Test Bank checkout. Please try again.'], 500);
        }

        $checkout = $response->json('data');
        $transaction->update(['provider_checkout_id' => $checkout['id'] ?? null]);

        return response()->json(['success' => true, 'checkout_url' => $checkout['attributes']['checkout_url'] ?? null]);
    }

    public function workspace(TestBank $testBank)
    {
        $user = Auth::user();
        $enrollment = $user->testBankEnrollments()
            ->where('test_bank_id', $testBank->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->firstOrFail();

        $testBank->load(['course.subjects' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order')]);
        $subjects = $testBank->course->subjects->map(function ($subject) use ($testBank) {
            $questionQuery = QuizQuestion::query()
                ->where('course_id', $testBank->course_id)
                ->where('status', 'approved')
                ->whereHas('topic', fn ($query) => $query->where('subject_id', $subject->id));
            $questionCount = (clone $questionQuery)->count();
            $testCount = (clone $questionQuery)->whereNotNull('topic_id')->distinct('topic_id')->count('topic_id');

            return [
                'id' => $subject->id,
                'code' => $subject->subject_code,
                'title' => $subject->title,
                'description' => $subject->description,
                'questionCount' => $questionCount,
                'premadeTestCount' => $testCount,
                'completedTests' => 0,
                'averageScore' => null,
                'progress' => 0,
            ];
        })->values();

        return response()->json(['success' => true, 'workspace' => [
            'id' => $testBank->id,
            'title' => $testBank->title,
            'code' => $testBank->code,
            'courseTitle' => $testBank->course->title,
            'accessDays' => $testBank->access_days,
            'enrolledAt' => $enrollment->enrolled_at?->toIso8601String(),
            'expiresAt' => $enrollment->expires_at?->toIso8601String(),
            'daysRemaining' => $enrollment->expires_at ? max(0, (int) ceil(now()->diffInDays($enrollment->expires_at, false))) : null,
            'readiness' => 0,
            'subjects' => $subjects,
            'history' => [],
        ]]);
    }

    public function enrolled()
    {
        $enrollments = Auth::user()->testBankEnrollments()
            ->with('testBank.course:id,title')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('testBank', fn ($query) => $query->where('status', 'active'))
            ->latest('enrolled_at')->get();

        return response()->json(['success'=>true, 'testBanks'=>$enrollments->map(fn ($enrollment) => [
            'id'=>$enrollment->testBank->id, 'title'=>$enrollment->testBank->title,
            'code'=>$enrollment->testBank->code, 'description'=>$enrollment->testBank->description,
            'course'=>[
                'id'=>$enrollment->testBank->course?->id,
                'title'=>$enrollment->testBank->course?->title,
            ],
            'enrolledAt'=>$enrollment->enrolled_at?->toIso8601String(),
            'expiresAt'=>$enrollment->expires_at?->toIso8601String(),
        ])->values()]);
    }

    public function adminIndex(Course $course)
    {
        return view('admin.content.test-banks', [
            'course' => $course,
            'testBanks' => $course->testBanks()->latest()->get(),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $data = $this->validatedData($request);
        $course->testBanks()->create($data + [
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Test Bank catalog created.');
    }

    public function update(Request $request, Course $course, TestBank $testBank)
    {
        abort_unless($testBank->course_id === $course->id, 404);
        $testBank->update($this->validatedData($request, $testBank));

        return back()->with('success', 'Test Bank catalog updated.');
    }

    public function destroy(Course $course, TestBank $testBank)
    {
        abort_unless($testBank->course_id === $course->id, 404);
        $testBank->delete();

        return back()->with('success', 'Test Bank catalog deleted.');
    }

    public function toggleStatus(Course $course, TestBank $testBank)
    {
        abort_unless($testBank->course_id === $course->id, 404);
        $testBank->update(['status' => $testBank->status === 'active' ? 'closed' : 'active']);

        return back()->with('success', $testBank->status === 'active'
            ? 'Test Bank activated and visible to learners.'
            : 'Test Bank deactivated and hidden from learners.');
    }

    private function validatedData(Request $request, ?TestBank $testBank = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('test_banks', 'code')->ignore($testBank?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'usd_price' => ['nullable', 'numeric', 'min:0'],
            'access_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);
    }
}
