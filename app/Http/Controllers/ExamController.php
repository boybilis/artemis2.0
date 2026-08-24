<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\Voucher;
use App\Models\Certificate;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\UserProgress;
use App\Models\CourseEnrollment;
use App\Models\CourseBatch;
use Carbon\Carbon;
use App\Services\AssessmentQuestionSelector;

class ExamController extends Controller
{
    private function activeBatch(int $userId, int $courseId): CourseBatch
    {
        return CourseBatch::where('course_id', $courseId)->whereHas('enrollments', fn ($query) => $query->where('user_id', $userId)->where('status', 'active')->where(fn ($active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())))->firstOrFail();
    }

    private function mockExamAttemptMessage(int $userId, Course $course): ?string
    {
        $attempts = QuizAttempt::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->where('assessment_type', 'final');
        if ((clone $attempts)->where('passed', true)->exists()) {
            return 'You already passed this Mock Exam. Your certificate is now available.';
        }
        $limit = $course->mock_exam_maximum_attempts;
        if ($limit !== null && (clone $attempts)->count() >= (int) $limit) {
            return 'You have used all allowed attempts for this Mock Exam.';
        }
        return null;
    }

    private function subtopicAssessment(Course $course, int $subtopicId): Subtopic
    {
        return Subtopic::whereHas('topic', fn ($query) => $query->where('course_id', $course->id))
            ->where('content_type', '!=', 'subtopic')->findOrFail($subtopicId);
    }

    private function allowedAttempts(Subtopic $item): ?int
    {
        if ($item->content_type === 'pre_test') return 1;
        return in_array($item->content_type, ['post_test', 'practice_test', 'mock_exam'], true) ? $item->maximum_attempts : null;
    }

    private function hasCompletedPreTest(int $userId, Subtopic $item): bool
    {
        $subjectId = $item->topic->subject_id;
        $batch = $this->activeBatch($userId, (int) $item->topic->course_id);
        return QuizAttempt::where('user_id', $userId)
            ->where('batch_id', $batch->id)
            ->where('assessment_type', 'pre_test')
            ->whereNotNull('points_earned')
            ->whereNotNull('points_possible')
            ->whereHas('topic', fn ($query) => $query->where('subject_id', $subjectId))
            ->exists();
    }

    private function courseMockPrerequisitesComplete(int $userId, int $courseId): bool
    {
        $topics = Topic::where('course_id', $courseId)
            ->where('status', 'approved')
            ->whereHas('subject', fn ($query) => $query->where('status', 'approved'))
            ->with(['subtopics' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
        $hasRequiredContent = false;

        foreach ($topics as $topic) {
            $unlockedIndex = (int) UserProgress::where('user_id', $userId)
                ->where('topic_id', $topic->id)
                ->value('max_unlocked_index');
            $flatIndex = 0;

            foreach ($topic->subtopics as $subtopic) {
                if ($subtopic->content_type === 'zoom_link') {
                    $hasRequiredContent = true;
                    if ($flatIndex >= $unlockedIndex) return false;
                    $flatIndex++;
                    continue;
                }
                if ($subtopic->content_type !== 'subtopic') {
                    if ($subtopic->content_type === 'mock_exam') continue;
                    $hasRequiredContent = true;
                    $hasScoredAttempt = QuizAttempt::where('user_id', $userId)
                        ->where('subtopic_id', $subtopic->id)
                        ->whereNotNull('points_earned')
                        ->whereNotNull('points_possible')
                        ->exists();
                    if ($flatIndex >= $unlockedIndex || ! $hasScoredAttempt) return false;
                    $flatIndex++;
                    continue;
                }

                $hasDocument = filled($subtopic->documentation_path);
                $hasVideo = filled($subtopic->video_url) || filled($subtopic->video_path);
                if ($hasDocument) {
                    $hasRequiredContent = true;
                    if ($flatIndex >= $unlockedIndex) return false;
                    $flatIndex++;
                }
                if ($hasVideo) {
                    $hasRequiredContent = true;
                    if ($flatIndex >= $unlockedIndex) return false;
                    $flatIndex++;
                }
                if (! $hasDocument && ! $hasVideo) $flatIndex++;
            }
        }

        return $hasRequiredContent;
    }

    private function mockPrerequisitesComplete(int $userId, Subtopic $mockExam): bool
    {
        return $this->courseMockPrerequisitesComplete($userId, (int) $mockExam->topic->course_id);
    }

    private function assessmentPrerequisiteMessage(int $userId, Subtopic $item): ?string
    {
        if ($item->content_type === 'pre_test') {
            if (! $this->policyTopicComplete($userId, $item)) {
                return 'Complete the Policy topic before opening the Pre-test.';
            }
            return null;
        }
        if (! $this->hasCompletedPreTest($userId, $item)) {
            return 'Complete the Pre-test before opening this assessment.';
        }
        if ($item->content_type === 'mock_exam' && ! $this->mockPrerequisitesComplete($userId, $item)) {
            return 'Complete every subject at 100% before opening the Mock Exam.';
        }
        return null;
    }

    private function policyTopicComplete(int $userId, Subtopic $preTest): bool
    {
        $policyTopic = Topic::where('course_id', $preTest->topic->course_id)
            ->where('subject_id', $preTest->topic->subject_id)
            ->where('status', 'approved')
            ->whereRaw('LOWER(title) LIKE ?', ['%policy%'])
            ->with(['subtopics' => fn ($query) => $query->where('status', 'approved')])
            ->first();

        if (! $policyTopic) return false;

        $requiredItems = $policyTopic->subtopics->sum(function (Subtopic $subtopic) {
            if ($subtopic->content_type !== 'subtopic') {
                return in_array($subtopic->content_type, ['mock_exam', 'pre_test'], true) ? 0 : 1;
            }
            $count = filled($subtopic->documentation_path) ? 1 : 0;
            $count += (filled($subtopic->video_url) || filled($subtopic->video_path)) ? 1 : 0;
            return $count ?: 1;
        });
        $unlockedIndex = (int) UserProgress::where('user_id', $userId)
            ->where('topic_id', $policyTopic->id)
            ->value('max_unlocked_index');

        return $requiredItems > 0 && $unlockedIndex >= $requiredItems;
    }

    public function getSubtopicQuestions(Request $request, $courseId, $subtopicId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        if (!$user->hasActiveEnrollment((int) $course->id)) return response()->json(['success' => false, 'message' => 'An active enrollment is required.'], 403);
        $item = $this->subtopicAssessment($course, (int) $subtopicId);
        if ($message = $this->assessmentPrerequisiteMessage($user->id, $item)) return response()->json(['success' => false, 'message' => $message], 403);
        $used = QuizAttempt::where('user_id', $user->id)->where('subtopic_id', $item->id)->count();
        $limit = $this->allowedAttempts($item);
        if ($limit !== null && $used >= $limit) return response()->json(['success' => false, 'message' => 'You have used all allowed attempts for this assessment.'], 422);
        // Generate a fresh database-randomized order for every new attempt, then
        // keep that exact order in the session for reliable scoring and review.
        $questionQuery = $item->questions()
            ->whereIn('question_type', ['subtopic_assessment', $item->content_type])
            ->where('status', 'approved');
        $questions = app(AssessmentQuestionSelector::class)->select($questionQuery, $item->assessment_question_count);
        if ($questions->isEmpty()) return response()->json(['success' => false, 'message' => 'No approved questions are available for this assessment.'], 422);
        session()->put("subtopic_exam_{$user->id}_{$item->id}", $questions->pluck('id')->all());
        session()->put("subtopic_exam_deadline_{$user->id}_{$item->id}", $item->assessment_time_limit_minutes ? now()->addMinutes((int) $item->assessment_time_limit_minutes)->timestamp : null);
        return response()->json(['success' => true, 'title' => $item->title, 'questions' => $questions->map(fn ($q) => [
            'id' => $q->id, 'question' => $q->question, 'imageUrl' => $q->image_path ? asset('storage/'.$q->image_path) : null,
            'options' => $q->options, 'responseType' => $q->response_type, 'responseConfig' => $q->learnerResponseConfig(), 'maximumPoints' => (float) $q->maximum_points,
        ]), 'attemptsUsed' => $used, 'maximumAttempts' => $limit, 'timeLimitMinutes' => $item->assessment_time_limit_minutes ? (int) $item->assessment_time_limit_minutes : null]);
    }

    public function submitSubtopic(Request $request, $courseId, $subtopicId)
    {
        $request->validate(['answers' => 'required|array']);
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        if (!$user->hasActiveEnrollment((int) $course->id)) return response()->json(['success' => false, 'message' => 'An active enrollment is required.'], 403);
        $item = $this->subtopicAssessment($course, (int) $subtopicId);
        if ($message = $this->assessmentPrerequisiteMessage($user->id, $item)) return response()->json(['success' => false, 'message' => $message], 403);
        $used = QuizAttempt::where('user_id', $user->id)->where('subtopic_id', $item->id)->count();
        $limit = $this->allowedAttempts($item);
        if ($limit !== null && $used >= $limit) return response()->json(['success' => false, 'message' => 'You have used all allowed attempts for this assessment.'], 422);
        $ids = session()->pull("subtopic_exam_{$user->id}_{$item->id}", []);
        $deadline = session()->pull("subtopic_exam_deadline_{$user->id}_{$item->id}");
        if (!$ids) return response()->json(['success' => false, 'message' => 'No active assessment session found.'], 400);
        if ($deadline && now()->timestamp > ((int) $deadline + 30)) return response()->json(['success' => false, 'message' => 'The time limit for this assessment has expired.'], 422);
        $questions = QuizQuestion::whereIn('id', $ids)->get()->keyBy('id');
        $score = 0; $earned = 0.0; $possible = 0.0; $incorrectQuestions = []; $reviewQuestions = [];
        foreach ($ids as $index => $id) {
            if ($question = $questions->get($id)) {
                $submitted = $request->input("answers.$index");
                $grade = $question->gradeAnswer($submitted);
                $earned += $grade['earned']; $possible += $grade['possible'];
                $reviewItem = ['question'=>$question->question,'imageUrl'=>$question->image_path ? asset('storage/'.$question->image_path) : null,'learnerAnswer'=>$question->formatAnswerForReview($submitted),'correctAnswer'=>$question->correctAnswerForReview(),'rationale'=>$question->rationale ?: 'No rationale was provided.','correct'=>(bool)$grade['correct']];
                $reviewQuestions[] = $reviewItem;
                if ($grade['correct']) $score++; else $incorrectQuestions[] = $reviewItem;
            }
        }
        $passingRatio = ((float) ($item->passing_percentage ?? 80)) / 100;
        $passed = $possible > 0 && ($earned / $possible) >= $passingRatio;
        QuizAttempt::create(['user_id'=>$user->id,'course_id'=>$course->id,'batch_id'=>CourseEnrollment::where('user_id',$user->id)->whereHas('batch',fn($query)=>$query->where('course_id',$course->id))->where('status','active')->value('batch_id'),'topic_id'=>$item->topic_id,'subtopic_id'=>$item->id,'assessment_type'=>$item->content_type,'score'=>$score,'total'=>count($ids),'points_earned'=>$earned,'points_possible'=>$possible,'passed'=>$passed,'review_data'=>$reviewQuestions]);
        return response()->json(['success'=>true,'passed'=>$passed,'score'=>$earned,'total'=>$possible,'attemptsUsed'=>$used+1,'maximumAttempts'=>$limit,'questions'=>$reviewQuestions,'incorrectQuestions'=>$incorrectQuestions]);
    }

    public function getQuestions(Request $request, $courseId)
    {
        $type = $request->query('type', 'final');
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!in_array($type, ['mid', 'final'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid exam type.'], 422);
        }

        if (! $user->hasActiveEnrollment((int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'Please subscribe to this course before taking its assessments.'], 403);
        }

        if ($type === 'final' && ! $this->courseMockPrerequisitesComplete($user->id, (int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'Complete every subject at 100% before opening the Mock Exam.'], 403);
        }
        if ($type === 'final' && ($message = $this->mockExamAttemptMessage($user->id, Course::findOrFail($courseId)))) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $questionQuery = QuizQuestion::where('course_id', $courseId)
            ->where('question_type', $type === 'mid' ? 'midterm' : 'final')
            ->where('status', 'approved');
        $questionLimit = $type === 'final' ? Course::findOrFail($courseId)->mock_exam_question_count : null;
        $questions = app(AssessmentQuestionSelector::class)->select($questionQuery, $questionLimit);

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No approved ' . ($type === 'mid' ? 'midterm' : 'final exam') . ' questions are available.',
            ], 422);
        }

        session()->put('exam_questions_' . $user->id . '_' . $courseId, $questions->pluck('id')->toArray());
        session()->put('exam_type_' . $user->id . '_' . $courseId, $type);
        $timeLimit = $type === 'final' ? Course::findOrFail($courseId)->mock_exam_time_limit_minutes : null;
        session()->put('exam_deadline_' . $user->id . '_' . $courseId, $timeLimit ? now()->addMinutes((int) $timeLimit)->timestamp : null);

        $formatted = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'question' => $q->question,
                'imageUrl' => $q->image_path ? asset('storage/' . $q->image_path) : null,
                'options' => $q->options,
                'responseType' => $q->response_type,
                'responseConfig' => $q->learnerResponseConfig(),
                'maximumPoints' => (float) $q->maximum_points,
            ];
        });

        return response()->json([
            'success' => true,
            'questions' => $formatted,
            'timeLimitMinutes' => $timeLimit ? (int) $timeLimit : null,
        ]);
    }

    public function submit(Request $request, $courseId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'answers' => 'required|array'
        ]);

        $type = session()->get('exam_type_' . $user->id . '_' . $courseId, 'final');
        $course = Course::findOrFail($courseId);
        if ($type === 'final' && ! $this->courseMockPrerequisitesComplete($user->id, (int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'Complete every subject at 100% before submitting the Mock Exam.'], 403);
        }
        if ($type === 'final' && ($message = $this->mockExamAttemptMessage($user->id, $course))) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }
        
        if ($type === 'final') {
            if (! $user->hasActiveEnrollment((int) $courseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active enrollment in this course is required to submit its final examination.'
                ], 400);
            }
        }

        $userAnswers = $request->input('answers');
        $questionIds = session()->get('exam_questions_' . $user->id . '_' . $courseId, []);
        $deadline = session()->pull('exam_deadline_' . $user->id . '_' . $courseId);
        
        if (empty($questionIds)) {
             return response()->json(['success' => false, 'message' => 'No active exam session found.'], 400);
        }
        if ($deadline && now()->timestamp > ((int) $deadline + 30)) {
            session()->forget(['exam_questions_' . $user->id . '_' . $courseId, 'exam_type_' . $user->id . '_' . $courseId]);
            return response()->json(['success' => false, 'message' => 'The time limit for this assessment has expired.'], 422);
        }

        $questionsDb = QuizQuestion::whereIn('id', $questionIds)->get()->keyBy('id');
        $score = 0;
        $total = count($questionIds);
        $pointsEarned = 0.0;
        $pointsPossible = 0.0;
        $incorrectQuestions = [];
        $reviewQuestions = [];

        foreach ($questionIds as $index => $qId) {
            $q = $questionsDb->get($qId);
            if ($q) {
                $submitted = $userAnswers[$index] ?? null;
                $grade = $q->gradeAnswer($submitted);
                $pointsEarned += $grade['earned'];
                $pointsPossible += $grade['possible'];
                $reviewItem = ['question'=>$q->question,'imageUrl'=>$q->image_path ? asset('storage/'.$q->image_path) : null,'learnerAnswer'=>$q->formatAnswerForReview($submitted),'correctAnswer'=>$q->correctAnswerForReview(),'rationale'=>$q->rationale ?: 'No rationale was provided.','correct'=>(bool)$grade['correct']];
                $reviewQuestions[] = $reviewItem;
                if ($grade['correct']) $score++; else $incorrectQuestions[] = $reviewItem;
            }
        }

        $passingRatio = $type === 'final'
            ? ((float) ($course->mock_exam_passing_percentage ?? 80)) / 100
            : 0.80;
        $passed = ($pointsPossible > 0 && ($pointsEarned / $pointsPossible) >= $passingRatio);

        QuizAttempt::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'batch_id' => CourseEnrollment::where('user_id', $user->id)->whereHas('batch', fn ($query) => $query->where('course_id', $courseId))->where('status', 'active')->value('batch_id'),
            'topic_id' => null, 
            'assessment_type' => $type === 'mid' ? 'midterm' : 'final',
            'score' => $score,
            'total' => $total,
            'points_earned' => $pointsEarned,
            'points_possible' => $pointsPossible,
            'passed' => $passed,
            'review_data' => $reviewQuestions,
        ]);

        $examName = $type === 'mid' ? 'Practice Test' : 'Mock Exam';
        
        AuditLog::create([
            'user_id' => $user->id,
            'action' => "$examName Completed",
            'description' => "Completed the {$course->title} $examName. Scored: $score/$total. Passed: " . ($passed ? 'Yes' : 'No'),
            'ip_address' => $request->ip()
        ]);

        $certificate = null;
        $passedMockExam = $type === 'final' && $passed;
        if ($passedMockExam) {
            $year = date('Y');
            $serial = str_pad(Certificate::count() + 1, 4, '0', STR_PAD_LEFT);
            $certCode = 'ARTEMIS-CERT-' . $year . '-' . $serial;

            $certificate = Certificate::firstOrCreate([
                'user_id' => $user->id,
                'course_id' => $courseId
            ], [
                'code' => $certCode,
                'issued_at' => Carbon::now()
            ]);

            if ($certificate->wasRecentlyCreated) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'Certificate Issued',
                    'description' => 'Issued certificate ' . $certCode . ' after passing the ' . $course->title . ' Mock Exam.',
                    'ip_address' => $request->ip()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'passed' => $passed,
            'score' => $pointsEarned,
            'total' => $pointsPossible,
            'certificate' => $certificate ? [
                'code' => $certificate->code,
                'issuedAt' => Carbon::parse($certificate->issued_at)->format('F d, Y'),
                'userName' => $user->name,
                'courseName' => $course->title
            ] : null,
            'incorrectQuestions' => $incorrectQuestions,
            'questions' => $reviewQuestions,
        ]);
    }

    public function subtopicSummary($courseId, $subtopicId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        if (! $user->hasActiveEnrollment((int) $course->id)) return response()->json(['success'=>false,'message'=>'An active enrollment is required.'], 403);
        $item = $this->subtopicAssessment($course, (int) $subtopicId);
        $batch = $this->activeBatch($user->id, $course->id);
        $attempt = QuizAttempt::where('user_id',$user->id)->where('course_id',$course->id)->where('batch_id',$batch->id)->where('subtopic_id',$item->id)->latest('id')->firstOrFail();
        $questions = collect($attempt->review_data ?? [])->map(fn ($item) => array_merge(['correct'=>false], $item))->values()->all();
        return response()->json(['success'=>true,'passed'=>(bool)$attempt->passed,'score'=>(float)($attempt->points_earned ?? $attempt->score),'total'=>(float)($attempt->points_possible ?? $attempt->total),'questions'=>$questions,'incorrectQuestions'=>collect($questions)->where('correct',false)->values()->all(),'reviewAvailable'=>$attempt->review_data !== null]);
    }

    public function mockExamSummary($courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        if (! $user->hasActiveEnrollment((int) $course->id)) return response()->json(['success'=>false,'message'=>'An active enrollment is required.'], 403);
        $batch = $this->activeBatch($user->id, $course->id);
        $attempt = QuizAttempt::where('user_id',$user->id)->where('course_id',$course->id)->where('batch_id',$batch->id)->where('assessment_type','final')->latest('id')->firstOrFail();
        $questions = collect($attempt->review_data ?? [])->map(fn ($item) => array_merge(['correct'=>false], $item))->values()->all();
        return response()->json(['success'=>true,'passed'=>(bool)$attempt->passed,'score'=>(float)($attempt->points_earned ?? $attempt->score),'total'=>(float)($attempt->points_possible ?? $attempt->total),'questions'=>$questions,'incorrectQuestions'=>collect($questions)->where('correct',false)->values()->all(),'reviewAvailable'=>$attempt->review_data !== null]);
    }

    public function getCertificate($courseId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $certificate = Certificate::where('user_id', $user->id)->where('course_id', $courseId)->first();
        $course = Course::find($courseId);

        if (!$certificate || !$course) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'certificate' => [
                'code' => $certificate->code,
                'issuedAt' => Carbon::parse($certificate->issued_at)->format('F d, Y'),
                'userName' => $user->name,
                'courseName' => $course->title
            ]
        ]);
    }
}
