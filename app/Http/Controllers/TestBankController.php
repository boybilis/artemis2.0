<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\TestBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TestBankController extends Controller
{
    public function catalog()
    {
        $testBanks = TestBank::query()
            ->with('course:id,title')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
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
            'startsAt' => $testBank->starts_at?->toIso8601String(),
            'endsAt' => $testBank->ends_at?->toIso8601String(),
            'course' => ['id' => $testBank->course?->id, 'title' => $testBank->course?->title],
        ])->values()]);
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
            'startsAt'=>$enrollment->testBank->starts_at?->toIso8601String(),
            'endsAt'=>$enrollment->testBank->ends_at?->toIso8601String(),
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
        $course->testBanks()->create($data + ['created_by' => $request->user()->id]);

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

    private function validatedData(Request $request, ?TestBank $testBank = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('test_banks', 'code')->ignore($testBank?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'usd_price' => ['nullable', 'numeric', 'min:0'],
            'access_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['draft', 'active', 'closed'])],
        ]);
    }
}
