<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\Subject;
use App\Models\TestBank;
use App\Models\TestBankQuestion;
use App\Models\TestBankQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if ((float) $testBank->price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Test Bank subscription is unavailable because the administrator has not configured a valid price yet.',
            ], 422);
        }

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
        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(25)
                ->connectTimeout(10)
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
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
        } catch (\Throwable $error) {
            $transaction->update(['status' => 'payment_gateway_unavailable']);
            Log::error('PayMongo Test Bank connection failed.', [
                'transaction' => $transaction->id,
                'error' => $error->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'The PayMongo payment service could not be reached. Please try again shortly.',
            ], 503);
        }

        if (!$response->successful()) {
            $transaction->update(['status' => 'payment_creation_failed']);
            Log::error('PayMongo Test Bank checkout failed.', ['transaction' => $transaction->id, 'response' => $response->json()]);
            $providerMessage = data_get($response->json(), 'errors.0.detail')
                ?: data_get($response->json(), 'errors.0.code');
            return response()->json([
                'success' => false,
                'message' => $providerMessage
                    ? 'PayMongo rejected the Test Bank checkout: ' . $providerMessage
                    : 'PayMongo could not create the Test Bank checkout. Please try again.',
            ], 422);
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

        $testBank->load([
            'course.subjects' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order'),
            'premadeQuizzes' => fn ($query) => $query->where('status', 'active')
                ->withCount(['questions' => fn ($questions) => $questions->where('test_bank_questions.status', 'active')])
                ->latest(),
        ]);
        $subjects = $testBank->course->subjects->map(function ($subject) use ($testBank) {
            $questionCount = $testBank->questions()
                ->whereHas('subjects', fn ($query) => $query->whereKey($subject->id))
                ->where('status', 'active')->count();
            $testCount = $testBank->premadeQuizzes()->where('status', 'active')
                ->whereJsonContains('subject_ids', $subject->id)->count();

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
            'premadeTests' => $testBank->premadeQuizzes->map(fn ($quiz) => [
                'id' => $quiz->id, 'title' => $quiz->title, 'description' => $quiz->description,
                'itemCount' => $quiz->questions_count, 'subjectIds' => $quiz->subject_ids,
                'randomized' => $quiz->randomize_questions,
            ])->values(),
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

    public function manage(Request $request, Course $course, TestBank $testBank)
    {
        $this->guardCatalog($course, $testBank);
        $subjects = $course->subjects()->where('status', 'approved')->orderBy('sort_order')->orderBy('title')->get();
        $questionTotal = $testBank->questions()->count();
        $questionQuery = $testBank->questions()->with([
            'subject:id,subject_code,title',
            'subjects:id,subject_code,title',
        ]);
        if ($search = trim((string) $request->query('search'))) {
            $questionQuery->where(function ($query) use ($search) {
                $query->where('question', 'like', "%{$search}%")
                    ->orWhere('rationale', 'like', "%{$search}%")
                    ->orWhere('options', 'like', "%{$search}%")
                    ->orWhereHas('subjects', fn ($subjects) => $subjects
                        ->where('subject_code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%"));
            });
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $questionQuery->where('status', $request->query('status'));
        }
        $sort = in_array($request->query('sort'), ['question', 'points', 'status'], true)
            ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $questions = $questionQuery->orderBy($sort, $direction)->orderByDesc('id')->paginate(20)->withQueryString();
        $quizzes = $testBank->premadeQuizzes()
            ->withCount(['questions' => fn ($query) => $query->where('test_bank_questions.status', 'active')])
            ->latest()->get();
        $questionPayload = $questions->mapWithKeys(fn (TestBankQuestion $question) => [$question->id => [
            'id' => $question->id,
            'question' => $question->question,
            'options' => $question->options,
            'correctAnswer' => $question->correct_answer,
            'points' => (float) $question->points,
            'rationale' => $question->rationale,
            'rationaleVideoUrl' => $question->rationale_video_url,
            'rationaleImageUrl' => $question->rationale_image_path ? asset('storage/'.$question->rationale_image_path) : null,
            'rationaleImageFilename' => $question->rationale_image_filename,
            'status' => $question->status,
            'imageUrl' => $question->image_path ? asset('storage/'.$question->image_path) : null,
            'imageFilename' => $question->image_filename,
            'subjectIds' => $question->subjects->pluck('id')->values(),
            'subjects' => $question->subjects->map(fn (Subject $subject) => [
                'id' => $subject->id, 'code' => $subject->subject_code, 'title' => $subject->title,
            ])->values(),
        ]]);

        return view('admin.content.test-bank-manage', compact('course', 'testBank', 'subjects', 'questions', 'quizzes', 'questionPayload', 'questionTotal'));
    }

    public function storeQuestion(Request $request, Course $course, TestBank $testBank)
    {
        $this->guardCatalog($course, $testBank);
        [$data, $subjectIds, $options] = $this->validatedQuestionData($request, $course);

        $questionData = [
            'course_id' => $course->id, 'subject_id' => $subjectIds->first(),
            'question' => $data['question'], 'options' => $options,
            'correct_answer' => $data['correct_answer'], 'points' => $data['points'] ?? 1,
            'rationale' => $data['rationale'] ?? null,
            'rationale_video_url' => $data['rationale_video_url'] ?? null,
            'status' => 'active', 'created_by' => $request->user()->id,
        ];
        if ($request->hasFile('question_image')) {
            $file = $request->file('question_image');
            $questionData['image_path'] = $file->store('test-bank-question-images', 'public');
            $questionData['image_filename'] = $file->getClientOriginalName();
        }
        if ($request->hasFile('rationale_image')) {
            $file = $request->file('rationale_image');
            $questionData['rationale_image_path'] = $file->store('test-bank-rationale-images', 'public');
            $questionData['rationale_image_filename'] = $file->getClientOriginalName();
        }

        $question = $testBank->questions()->create($questionData);
        $question->subjects()->sync($subjectIds);

        return back()->with('success', 'Multiple-choice question added to the Test Bank.');
    }

    public function updateQuestion(Request $request, Course $course, TestBank $testBank, TestBankQuestion $question)
    {
        $this->guardQuestion($course, $testBank, $question);
        [$data, $subjectIds, $options] = $this->validatedQuestionData($request, $course);
        $questionData = [
            'subject_id' => $subjectIds->first(),
            'question' => $data['question'],
            'options' => $options,
            'correct_answer' => $data['correct_answer'],
            'points' => $data['points'] ?? 1,
            'rationale' => $data['rationale'] ?? null,
            'rationale_video_url' => $data['rationale_video_url'] ?? null,
        ];

        if ($request->hasFile('question_image')) {
            if ($question->image_path) Storage::disk('public')->delete($question->image_path);
            $file = $request->file('question_image');
            $questionData['image_path'] = $file->store('test-bank-question-images', 'public');
            $questionData['image_filename'] = $file->getClientOriginalName();
        } elseif ($request->boolean('remove_question_image')) {
            if ($question->image_path) Storage::disk('public')->delete($question->image_path);
            $questionData['image_path'] = null;
            $questionData['image_filename'] = null;
        }
        if ($request->hasFile('rationale_image')) {
            if ($question->rationale_image_path) Storage::disk('public')->delete($question->rationale_image_path);
            $file = $request->file('rationale_image');
            $questionData['rationale_image_path'] = $file->store('test-bank-rationale-images', 'public');
            $questionData['rationale_image_filename'] = $file->getClientOriginalName();
        } elseif ($request->boolean('remove_rationale_image')) {
            if ($question->rationale_image_path) Storage::disk('public')->delete($question->rationale_image_path);
            $questionData['rationale_image_path'] = null;
            $questionData['rationale_image_filename'] = null;
        }

        $question->update($questionData);
        $question->subjects()->sync($subjectIds);

        return back()->with('success', 'Test Bank question updated.');
    }

    public function importQuestions(Request $request, Course $course, TestBank $testBank)
    {
        $this->guardCatalog($course, $testBank);
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($request->file('csv_file')->getRealPath(), 'rb');
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
        $subjectHeader = in_array('subject_codes', $headers, true) ? 'subject_codes' : 'subject_code';
        $required = [$subjectHeader, 'question', 'option_a', 'option_b', 'correct_answer'];
        if (array_diff($required, $headers)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must include subject_codes (or subject_code), question, option_a, option_b, and correct_answer.']);
        }

        $subjects = $course->subjects()->get()->keyBy(fn ($subject) => strtolower(trim($subject->subject_code)));
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            if (count($rows) >= 5000) { fclose($handle); return back()->withErrors(['csv_file' => 'A CSV upload is limited to 5,000 questions.']); }
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            $subjectCodes = collect(explode('|', (string) ($row[$subjectHeader] ?? '')))
                ->map(fn ($code) => strtolower(trim($code)))->filter()->unique()->values();
            $rowSubjects = $subjectCodes->map(fn ($code) => $subjects->get($code))->filter();
            $options = collect(range('a', 'h'))->map(fn ($letter) => trim((string) ($row['option_'.$letter] ?? '')))->filter()->values()->all();
            $correct = strtoupper(trim((string) ($row['correct_answer'] ?? '')));
            $correctIndex = ord($correct) - ord('A');
            $points = trim((string) ($row['points'] ?? '')) === '' ? 1 : filter_var($row['points'], FILTER_VALIDATE_FLOAT);
            if ($subjectCodes->isEmpty() || $rowSubjects->count() !== $subjectCodes->count() || trim((string) ($row['question'] ?? '')) === '' || count($options) < 2 || $correctIndex < 0 || $correctIndex >= count($options) || $points === false || $points < 0.01 || $points > 1000) {
                fclose($handle);
                return back()->withErrors(['csv_file' => "Invalid data on CSV row {$line}. Check the subject codes, question, choices, answer letter, and points."]);
            }
            $rows[] = [
                'subject_ids' => $rowSubjects->pluck('id')->values()->all(),
                'test_bank_id' => $testBank->id, 'course_id' => $course->id, 'subject_id' => $rowSubjects->first()->id,
                'question' => trim($row['question']), 'options' => $options, 'correct_answer' => $correctIndex,
                'points' => $points,
                'rationale' => trim((string) ($row['rationale'] ?? '')) ?: null,
                'status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        fclose($handle);
        if (!$rows) return back()->withErrors(['csv_file' => 'The CSV contains no question rows.']);
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $subjectIds = $row['subject_ids'];
                unset($row['subject_ids']);
                $question = TestBankQuestion::create($row);
                $question->subjects()->sync($subjectIds);
            }
        });

        return back()->with('success', count($rows).' Test Bank questions imported.');
    }

    public function toggleQuestionStatus(Course $course, TestBank $testBank, TestBankQuestion $question)
    {
        $this->guardQuestion($course, $testBank, $question);
        $question->update(['status' => $question->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', $question->status === 'active'
            ? 'Test Bank question restored.'
            : 'Test Bank question archived and removed from learner tests.');
    }

    public function storeQuiz(Request $request, Course $course, TestBank $testBank)
    {
        $this->guardCatalog($course, $testBank);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'item_count' => ['required', 'integer', 'min:1', 'max:500'],
            'subject_ids' => ['required', 'array', 'min:1'], 'subject_ids.*' => ['integer'],
        ]);
        $validSubjectIds = $course->subjects()->whereIn('id', $data['subject_ids'])->pluck('id');
        if ($validSubjectIds->count() !== count(array_unique($data['subject_ids']))) abort(422, 'One or more subjects do not belong to this course.');
        $questionIds = $testBank->questions()->where('status', 'active')
            ->whereHas('subjects', fn ($query) => $query->whereIn('subjects.id', $validSubjectIds))
            ->inRandomOrder()->limit($data['item_count'])->pluck('id');
        if ($questionIds->isEmpty()) return back()->withErrors(['subject_ids' => 'The selected subjects do not have active Test Bank questions yet.']);

        DB::transaction(function () use ($request, $testBank, $data, $validSubjectIds, $questionIds) {
            $quiz = $testBank->premadeQuizzes()->create([
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'item_count' => $questionIds->count(), 'subject_ids' => $validSubjectIds->values()->all(),
                'randomize_questions' => true, 'status' => 'active', 'created_by' => $request->user()->id,
            ]);
            $quiz->questions()->sync($questionIds);
        });
        $message = $questionIds->count() < $data['item_count']
            ? "Premade quiz saved with all {$questionIds->count()} available questions."
            : 'Premade randomized quiz saved.';
        return back()->with('success', $message);
    }

    public function destroyQuiz(Course $course, TestBank $testBank, TestBankQuiz $quiz)
    {
        $this->guardCatalog($course, $testBank);
        abort_unless($quiz->test_bank_id === $testBank->id, 404);
        $quiz->delete();
        return back()->with('success', 'Premade quiz deleted.');
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

    private function guardCatalog(Course $course, TestBank $testBank): void
    {
        abort_unless($testBank->course_id === $course->id, 404);
    }

    private function guardQuestion(Course $course, TestBank $testBank, TestBankQuestion $question): void
    {
        $this->guardCatalog($course, $testBank);
        abort_unless($question->test_bank_id === $testBank->id && $question->course_id === $course->id, 404);
    }

    private function validatedQuestionData(Request $request, Course $course): array
    {
        $data = $request->validate([
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct'],
            'question' => ['required', 'string', 'max:10000'],
            'options' => ['required', 'array', 'min:2', 'max:8'],
            'options.*' => ['nullable', 'string', 'max:3000'],
            'correct_answer' => ['required', 'integer', 'min:0', 'max:7'],
            'points' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'rationale' => ['nullable', 'string', 'max:10000'],
            'rationale_video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rationale_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_rationale_image' => ['nullable', 'boolean'],
            'question_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_question_image' => ['nullable', 'boolean'],
        ]);
        $subjectIds = $course->subjects()->whereIn('id', $data['subject_ids'])->pluck('id');
        abort_unless($subjectIds->count() === count($data['subject_ids']), 404);
        $rawOptions = collect($data['options'])->map(fn ($option) => trim((string) $option));
        $lastOption = $rawOptions->search(fn ($option, $index) => $rawOptions->slice($index + 1)->filter()->isEmpty() && $option !== '');
        $options = $lastOption === false ? [] : $rawOptions->take($lastOption + 1)->all();
        if (in_array('', $options, true)) {
            throw ValidationException::withMessages(['options' => 'Choices must be entered in order without empty choices between them.']);
        }
        if (count($options) < 2 || !array_key_exists($data['correct_answer'], $options)) {
            throw ValidationException::withMessages(['correct_answer' => 'Provide at least two choices and select a valid correct answer.']);
        }

        return [$data, $subjectIds, $options];
    }
}
