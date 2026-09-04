<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use App\Models\Topic;
use App\Models\Course;
use App\Models\UserProgress;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Certificate;
use App\Models\Subject;
use App\Models\CourseEnrollment;
use App\Models\Subtopic;
use App\Models\CourseBatch;
use App\Services\AssessmentQuestionSelector;
use App\Services\GoogleDriveVideoService;

class CourseController extends Controller
{
    public function getCourses()
    {
        $user = Auth::user();
        $availableCourses = Course::available()->with(['batches' => fn ($query) => $query->available()->orderBy('starts_at')->orderBy('name')])->get();
        $courseIds = $availableCourses->pluck('id');
        $userCertificates = Certificate::with('course:id,title')
            ->where('user_id', $user->id)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn ($certificate) => [
                'id' => $certificate->id,
                'courseId' => $certificate->course_id,
                'courseName' => $certificate->course?->title ?? 'Course',
                'code' => $certificate->code,
                'issuedAt' => $certificate->issued_at?->format('F j, Y'),
            ]);
        $certificatesByCourse = $userCertificates->keyBy('courseId');
        $activeLearners = CourseEnrollment::with('batch:id,course_id')
            ->whereHas('batch', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['batch_id', 'user_id'])
            ->groupBy(fn ($enrollment) => $enrollment->batch?->course_id)
            ->map(fn ($enrollments) => $enrollments->pluck('user_id')->unique()->values());
        $mockAttempts = QuizAttempt::whereIn('course_id', $courseIds)
            ->where('assessment_type', 'final')
            ->get(['id', 'course_id', 'user_id', 'score', 'total', 'points_earned', 'points_possible'])
            ->groupBy('course_id');

        $rankings = $courseIds->mapWithKeys(function ($courseId) use ($activeLearners, $mockAttempts) {
            $eligible = $activeLearners->get($courseId, collect());
            $bestScores = $mockAttempts->get($courseId, collect())
                ->whereIn('user_id', $eligible)
                ->groupBy('user_id')
                ->map(function ($attempts) {
                    return $attempts->map(function ($attempt) {
                        $possible = (float) ($attempt->points_possible ?: $attempt->total);
                        $earned = (float) ($attempt->points_earned ?? $attempt->score);
                        return ['percentage' => $possible > 0 ? ($earned / $possible) * 100 : 0, 'earned' => $earned];
                    })->sortByDesc(fn ($score) => [$score['percentage'], $score['earned']])->first();
                })
                ->sort(function ($left, $right) {
                    return [$right['percentage'], $right['earned']] <=> [$left['percentage'], $left['earned']];
                });

            $positions = [];
            foreach ($bestScores->keys()->values() as $index => $learnerId) $positions[(int) $learnerId] = $index + 1;
            return [$courseId => ['positions' => $positions, 'total' => $bestScores->count()]];
        });

        $courses = $availableCourses->flatMap(function ($course) use ($user, $rankings, $certificatesByCourse) {
            $enrollment = $user->enrollments()->whereHas('batch', fn ($query) => $query->where('course_id', $course->id))->latest()->first();
            if ($enrollment?->isActive() && $enrollment->batch_id && !$course->batches->contains('id', $enrollment->batch_id)) {
                if ($enrolledBatch = CourseBatch::find($enrollment->batch_id)) $course->batches->push($enrolledBatch);
            }
            $courseRanking = $rankings->get($course->id, ['positions'=>[], 'total'=>0]);
            $international = strtoupper((string) $user->country_code) !== 'PH';
            $base = collect($course->toArray())->except('batches')->all();
            return $course->batches->unique('id')->map(function ($batch) use ($base, $course, $enrollment, $courseRanking, $user, $certificatesByCourse, $international) {
                $isEnrolled = ($enrollment?->isActive() ?? false) && (int) $enrollment->batch_id === (int) $batch->id;
                return array_merge($base, [
                    'batch_id'=>$batch->id, 'batch_name'=>$batch->name, 'batch_code'=>$batch->code,
                    'batch_description'=>$batch->description, 'batch_starts_at'=>$batch->starts_at?->toIso8601String(),
                    'batch_ends_at'=>$batch->ends_at?->toIso8601String(), 'batch_capacity'=>$batch->capacity,
                    'batch_schedule_day'=>$batch->schedule_day, 'batch_start_time'=>$batch->start_time,
                    'batch_end_time'=>$batch->end_time, 'batch_modality'=>$batch->modality,
                    'batch_status'=>$batch->status, 'batch_price'=>(float) $batch->price,
                    'batch_usd_price'=>$batch->usd_price !== null ? (float) $batch->usd_price : null,
                    'is_enrolled'=>$isEnrolled, 'enrolled_at'=>$isEnrolled ? $enrollment?->enrolled_at?->toIso8601String() : null,
                    'enrollment_expires_at'=>$isEnrolled ? $enrollment?->expires_at?->toIso8601String() : null,
                    'mock_exam_rank'=>$isEnrolled ? ($courseRanking['positions'][$user->id] ?? null) : null,
                    'mock_exam_ranked_count'=>$isEnrolled ? $courseRanking['total'] : 0,
                    'has_certificate'=>$isEnrolled && $certificatesByCourse->has($course->id),
                    'certificate'=>$isEnrolled ? $certificatesByCourse->get($course->id) : null,
                    'display_price'=>(float) ($international ? ($batch->usd_price ?? $batch->price) : $batch->price),
                    'currency_symbol'=>$international ? '$' : '₱', 'currency_code'=>$international ? 'USD' : 'PHP',
                    'billing_price'=>(float) $batch->price, 'billing_currency_symbol'=>'₱', 'billing_currency_code'=>'PHP',
                ]);
            });
        });
        return response()->json([
            'success' => true,
            'courses' => $courses,
            'certificates' => $userCertificates->values(),
        ]);
    }

    public function getPublicCourses()
    {
        $courses = Course::available()->get(['id', 'title', 'description', 'thumbnail_url']);
        return response()->json([
            'success' => true,
            'courses' => $courses
        ]);
    }

    public function getTopics($courseId)
    {
        if (! Auth::user()->hasActiveEnrollment((int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'Please subscribe to this course to access its modules.'], 403);
        }
        $course = Course::findOrFail($courseId);
        $user = Auth::user();
        $activeBatch = CourseBatch::where('course_id', $courseId)
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->firstOrFail();
        $topics = Topic::where('course_id', $courseId)->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('subject_id')->orWhereHas('subject', fn ($subject) => $subject->where('status', 'approved')))
            ->orderBy('sort_order')->get();

        $completedPreTestSubjectIds = QuizAttempt::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('batch_id', $activeBatch->id)
            ->where('assessment_type', 'pre_test')
            ->whereNotNull('points_earned')
            ->whereNotNull('points_possible')
            ->whereHas('topic', fn ($query) => $query->whereNotNull('subject_id'))
            ->with('topic:id,subject_id')
            ->get()
            ->pluck('topic.subject_id')
            ->filter()
            ->unique();

        $formattedTopics = $topics->map(function ($topic) use ($user, $activeBatch, $completedPreTestSubjectIds) {
            $subtopics = $topic->subtopics()->where('status', 'approved')->get()->map(function ($sub) use ($user, $activeBatch) {
                $driveFileId = app(GoogleDriveVideoService::class)->fileIdFromUrl($sub->video_url);
                $protectedVideoUrl = ($sub->video_path || ($driveFileId && app(GoogleDriveVideoService::class)->enabled()))
                    ? URL::temporarySignedRoute(
                        'learning.video',
                        now()->addMinutes((int) config('session.lifetime', 120)),
                        ['subtopic' => $sub->id, 'session_key' => $this->videoSessionKey()]
                    ) : null;
                $protectedDocumentUrl = $this->publicStorageRelativePath($sub->documentation_path)
                    ? URL::temporarySignedRoute(
                        'learning.document.subtopic',
                        now()->addMinutes((int) config('session.lifetime', 120)),
                        ['subtopic' => $sub->id, 'session_key' => $this->documentSessionKey()]
                    ) : $sub->documentation_path;
                $assessmentAttempts = $sub->content_type === 'subtopic'
                    ? collect()
                    : QuizAttempt::where('user_id', $user->id)
                        ->where('batch_id', $activeBatch->id)
                        ->where('subtopic_id', $sub->id)
                        ->latest('id')
                        ->get();
                $attemptsUsed = $assessmentAttempts->count();
                $latestAttempt = $assessmentAttempts->first();
                $maximumAttempts = $sub->content_type === 'pre_test' ? 1 : $sub->maximum_attempts;
                return [
                    'id'                    => $sub->id,
                    'title'                 => $sub->title,
                    'sort_order'            => $sub->sort_order,
                    'videoUrl'              => $driveFileId && $protectedVideoUrl ? null : $sub->video_url,
                    'videoUploadUrl'        => $protectedVideoUrl,
                    'videoFilename'         => $sub->video_filename,
                    'documentationPath'     => $protectedDocumentUrl,
                    'documentationFilename' => $sub->documentation_filename,
                    'contentType'           => $sub->content_type,
                    'instructions'          => $sub->instructions,
                    'zoomUrl'               => $sub->zoom_url,
                    'zoomDescription'       => $sub->zoom_description,
                    'zoomStartsAt'          => $sub->zoom_starts_at?->toIso8601String(),
                    'zoomEndsAt'            => $sub->zoom_ends_at?->toIso8601String(),
                    'maximumAttempts'       => $maximumAttempts,
                    'attemptsUsed'          => $attemptsUsed,
                    'latestScore'           => $latestAttempt ? (float) ($latestAttempt->points_earned ?? $latestAttempt->score) : null,
                    'latestTotal'           => $latestAttempt ? (float) ($latestAttempt->points_possible ?? $latestAttempt->total) : null,
                    'latestPassed'          => $latestAttempt?->passed,
                    'questionCount'         => $sub->questions()->where('status', 'approved')->count(),
                    'timeLimitMinutes'      => $sub->assessment_time_limit_minutes ? (int) $sub->assessment_time_limit_minutes : null,
                ];
            });

            $quizQuery = $topic->quizQuestions()
                ->where('question_type', 'quiz')
                ->where('status', 'approved');
            $quizQuestions = app(AssessmentQuestionSelector::class)->select($quizQuery, $topic->assessment_question_count);
            session()->put("topic_quiz_{$user->id}_{$topic->id}", $quizQuestions->pluck('id')->all());

            return [
                'id'         => $topic->id,
                'subjectId'  => $topic->subject_id,
                'isPolicyTopic' => str_contains(strtolower(trim($topic->title)), 'policy'),
                'isLocked' => $topic->subject_id !== null
                    && ! str_contains(strtolower(trim($topic->title)), 'policy')
                    && ! $completedPreTestSubjectIds->contains($topic->subject_id),
                'title'      => $topic->title,
                'sort_order' => $topic->sort_order,
                'subtopics'  => $subtopics,
                'videoUrl'              => $topic->video_url,
                'videos'                => $topic->videos,
                'documentationPath'     => $this->publicStorageRelativePath($topic->documentation_path)
                    ? URL::temporarySignedRoute(
                        'learning.document.topic',
                        now()->addMinutes((int) config('session.lifetime', 120)),
                        ['topic' => $topic->id, 'session_key' => $this->documentSessionKey()]
                    ) : $topic->documentation_path,
                'documentationFilename' => $topic->documentation_filename,
                'quizTimeLimitMinutes' => $topic->assessment_time_limit_minutes ? (int) $topic->assessment_time_limit_minutes : null,
                'quiz' => $quizQuestions->map(function ($q) {
                    return [
                        'id'       => $q->id,
                        'question' => $q->question,
                        'imageUrl' => $q->image_path ? asset('storage/' . $q->image_path) : null,
                        'options'  => $q->options,
                        'responseType' => $q->response_type,
                        'responseConfig' => $q->learnerResponseConfig(),
                        'maximumPoints' => (float) $q->maximum_points,
                        'correctAnswers' => $q->correct_answers ?: [(int) $q->answer],
                        'rationale' => $q->rationale,
                    ];
                })
            ];
        });

        $completedTopicIds = UserProgress::where('user_id', $user->id)->where('course_id', $courseId)->pluck('topic_id');
        $subjects = Subject::where('course_id', $courseId)->where('status', 'approved')->orderBy('sort_order')->orderBy('title')->get()->map(function ($subject) use ($topics, $completedTopicIds) {
            $subjectTopicIds = $topics->where('subject_id', $subject->id)->pluck('id');
            $total = $subjectTopicIds->count();
            $completed = $subjectTopicIds->intersect($completedTopicIds)->count();
            return [
                'id' => $subject->id, 'code' => $subject->subject_code, 'title' => $subject->title,
                'description' => $subject->description, 'topicCount' => $total, 'completedTopics' => $completed,
                'progressPercentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ];
        });

        $mockAttempts = QuizAttempt::where('user_id', $user->id)->where('course_id', $courseId)->where('batch_id', $activeBatch->id)
            ->where('assessment_type', 'final');
        $mockAttemptsUsed = (clone $mockAttempts)->count();
        $mockExamPassed = (clone $mockAttempts)->where('passed', true)->exists();
        $latestMockAttempt = (clone $mockAttempts)->latest('id')->first();
        $mockMaximumAttempts = $course->mock_exam_maximum_attempts !== null
            ? (int) $course->mock_exam_maximum_attempts : null;

        return response()->json([
            'success' => true,
            'topics'  => $formattedTopics,
            'subjects' => $subjects,
            'mockExamQuestionCount' => min(
                (int) ($course->mock_exam_question_count ?: PHP_INT_MAX),
                QuizQuestion::where('course_id', $courseId)->where('question_type', 'final')->where('status', 'approved')->count()
            ),
            'mockExamLatestResult' => $latestMockAttempt ? [
                'score' => (float) ($latestMockAttempt->points_earned ?? $latestMockAttempt->score),
                'total' => (float) ($latestMockAttempt->points_possible ?? $latestMockAttempt->total),
                'passed' => (bool) $latestMockAttempt->passed,
            ] : null,
            'mockExamAttemptsUsed' => $mockAttemptsUsed,
            'mockExamMaximumAttempts' => $mockMaximumAttempts,
            'mockExamTimeLimitMinutes' => $course->mock_exam_time_limit_minutes ? (int) $course->mock_exam_time_limit_minutes : null,
            'mockExamPassed' => $mockExamPassed,
            'mockExamCertificateAvailable' => Certificate::where('user_id', $user->id)
                ->where('course_id', $courseId)->exists(),
        ]);
    }

    public function assessmentProgressReport($courseId)
    {
        $user = Auth::user();
        if (! $user->hasActiveEnrollment((int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'An active enrollment is required.'], 403);
        }
        $batch = CourseBatch::where('course_id', $courseId)
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->firstOrFail();
        $rankingAttempts = QuizAttempt::where('course_id', $courseId)
            ->whereIn('assessment_type', ['pre_test', 'post_test', 'practice_test', 'final'])
            ->get(['user_id','batch_id','subtopic_id','assessment_type','score','total','points_earned','points_possible']);
        $assessmentKeyFor = fn (QuizAttempt $attempt) => $attempt->assessment_type === 'final' ? 'mock_exam' : 'subtopic_'.$attempt->subtopic_id;
        $buildBestScores = function ($pool) use ($assessmentKeyFor) {
            $scores = [];
            foreach ($pool as $rankingAttempt) {
                $possible = (float) ($rankingAttempt->points_possible ?? $rankingAttempt->total);
                if ($possible <= 0) continue;
                $percentage = ((float) ($rankingAttempt->points_earned ?? $rankingAttempt->score) / $possible) * 100;
                $key = $assessmentKeyFor($rankingAttempt);
                $scores[$key][$rankingAttempt->user_id] = max($scores[$key][$rankingAttempt->user_id] ?? -1, $percentage);
            }
            return $scores;
        };
        $batchBestScores = $buildBestScores($rankingAttempts->where('batch_id', $batch->id));
        $courseBestScores = $buildBestScores($rankingAttempts);
        $rankFor = function (array $scorePool, string $key, int $userId, float $percentage) {
            $scores = $scorePool[$key] ?? [];
            $scores[$userId] = $percentage;
            return ['rank'=>1 + collect($scores)->filter(fn ($score) => $score > $percentage)->count(),'total'=>count($scores)];
        };
        $attemptCounters = [];
        $attempts = QuizAttempt::with(['topic.subject', 'subtopic'])
            ->where('user_id', $user->id)->where('course_id', $courseId)->where('batch_id', $batch->id)
            ->whereIn('assessment_type', ['pre_test', 'post_test', 'practice_test', 'final'])
            ->oldest('created_at')->oldest('id')->get()
            ->map(function (QuizAttempt $attempt) use (&$attemptCounters, $batchBestScores, $courseBestScores, $rankFor) {
                $isMock = $attempt->assessment_type === 'final';
                $assessmentKey = $isMock ? 'mock_exam' : 'subtopic_'.$attempt->subtopic_id;
                $attemptCounters[$assessmentKey] = ($attemptCounters[$assessmentKey] ?? 0) + 1;
                $labels = ['pre_test'=>'Pre-Test','post_test'=>'Post-Test','practice_test'=>'Practice Test','final'=>'Mock Exam'];
                $earned = (float) ($attempt->points_earned ?? $attempt->score);
                $possible = (float) ($attempt->points_possible ?? $attempt->total);
                $exactPercentage = $possible > 0 ? ($earned / $possible) * 100 : 0;
                $batchRank = $rankFor($batchBestScores, $assessmentKey, (int) $attempt->user_id, $exactPercentage);
                $courseRank = $rankFor($courseBestScores, $assessmentKey, (int) $attempt->user_id, $exactPercentage);
                return [
                    'assessmentKey'=>$assessmentKey,'assessmentType'=>$attempt->assessment_type,
                    'assessmentLabel'=>$labels[$attempt->assessment_type] ?? 'Assessment',
                    'assessmentTitle'=>$isMock ? 'Comprehensive Mock Exam' : ($attempt->subtopic?->title ?: ($labels[$attempt->assessment_type] ?? 'Assessment')),
                    'subjectId'=>$attempt->topic?->subject_id,'subjectCode'=>$attempt->topic?->subject?->subject_code,
                    'subjectTitle'=>$attempt->topic?->subject?->title,'topicTitle'=>$attempt->topic?->title,
                    'attemptNumber'=>$attemptCounters[$assessmentKey],'score'=>$earned,'total'=>$possible,
                    'percentage'=>(int) round($exactPercentage),'batchRank'=>$batchRank,'courseRank'=>$courseRank,
                    'passed'=>(bool)$attempt->passed,'takenAt'=>$attempt->created_at?->toIso8601String(),
                ];
            })->values();

        return response()->json([
            'success'=>true,'course'=>Course::findOrFail($courseId)->only(['id','title']),
            'batch'=>$batch->only(['id','name','code']),'attempts'=>$attempts,
        ]);
    }

    public function streamSubtopicVideo(Request $request, Subtopic $subtopic, GoogleDriveVideoService $drive)
    {
        abort_unless(hash_equals($this->videoSessionKey(), (string) $request->query('session_key')), 403, 'This video link belongs to a different or expired session.');

        $subtopic->loadMissing('topic');
        abort_unless($subtopic->status === 'approved' && $subtopic->topic?->status === 'approved', 404);
        abort_unless(Auth::user()->hasActiveEnrollment((int) $subtopic->topic->course_id), 403, 'An active enrollment is required to view this video.');
        if (! $subtopic->video_path) {
            $fileId = $drive->fileIdFromUrl($subtopic->video_url);
            abort_unless($fileId, 404);
            $file = $drive->authorizedVideo($fileId);
            $size = (int) $file['size'];
            [$start, $end, $partial] = $this->requestedVideoRange($request, $size);
            $length = $end - $start + 1;
            $headers = [
                'Content-Type' => $file['mimeType'] ?: 'video/mp4',
                'Content-Length' => (string) $length,
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="' . addcslashes(basename((string) $file['name']), '"\\') . '"',
                'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Accel-Buffering' => 'no',
            ];
            if ($partial) $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            return response()->stream(fn () => $drive->stream($fileId, $start, $end), $partial ? 206 : 200, $headers);
        }

        abort_unless(Storage::disk('public')->exists($subtopic->video_path), 404);

        $response = response()->file(Storage::disk('public')->path($subtopic->video_path), [
            'Content-Type' => Storage::disk('public')->mimeType($subtopic->video_path) ?: 'video/mp4',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');
        return $response;
    }

    public function streamSubtopicDocument(Request $request, Subtopic $subtopic)
    {
        $this->authorizeDocumentSession($request);
        $subtopic->loadMissing('topic');
        abort_unless($subtopic->status === 'approved' && $subtopic->topic?->status === 'approved', 404);
        abort_unless(Auth::user()->hasActiveEnrollment((int) $subtopic->topic->course_id), 403, 'An active enrollment is required to view this document.');

        return $this->privateDocumentResponse($subtopic->documentation_path, $subtopic->documentation_filename);
    }

    public function streamTopicDocument(Request $request, Topic $topic)
    {
        $this->authorizeDocumentSession($request);
        abort_unless($topic->status === 'approved', 404);
        abort_unless(Auth::user()->hasActiveEnrollment((int) $topic->course_id), 403, 'An active enrollment is required to view this document.');

        return $this->privateDocumentResponse($topic->documentation_path, $topic->documentation_filename);
    }

    private function authorizeDocumentSession(Request $request): void
    {
        abort_unless(hash_equals($this->documentSessionKey(), (string) $request->query('session_key')), 403, 'This document link belongs to a different or expired session.');
    }

    private function privateDocumentResponse(?string $storedPath, ?string $filename)
    {
        $path = $this->publicStorageRelativePath($storedPath);
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'The requested document was not found.');

        $response = response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addcslashes($filename ?: basename($path), '"\\') . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function publicStorageRelativePath(?string $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/storage/')) return null;
        $relative = ltrim(substr($path, strlen('/storage/')), '/');

        return $relative !== '' && ! str_contains($relative, '..') ? $relative : null;
    }

    private function requestedVideoRange(Request $request, int $size): array
    {
        // Start with a small segment so playback can begin quickly, then use
        // larger segments to reduce round trips during continuous playback.
        $initialChunkSize = 1 * 1024 * 1024;
        $regularChunkSize = 8 * 1024 * 1024;
        $range = $request->header('Range');
        if (! $range) return [0, $size - 1, false];
        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches) || ($matches[1] === '' && $matches[2] === '')) {
            abort(416, 'Invalid video range.', ['Content-Range' => "bytes */{$size}"]);
        }
        if ($matches[1] === '') {
            $suffix = min((int) $matches[2], $size, $regularChunkSize);
            return [$size - $suffix, $size - 1, true];
        }
        $start = (int) $matches[1];
        if ($start >= $size) abort(416, 'Video range is outside the file.', ['Content-Range' => "bytes */{$size}"]);
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        if ($end < $start) abort(416, 'Invalid video range.', ['Content-Range' => "bytes */{$size}"]);
        $chunkSize = $start === 0 ? $initialChunkSize : $regularChunkSize;
        $end = min($end, $start + $chunkSize - 1);
        return [$start, $end, true];
    }

    private function videoSessionKey(): string
    {
        $session = request()->session();
        $token = $session->get('video_access_token');
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->put('video_access_token', $token);
        }
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function documentSessionKey(): string
    {
        $session = request()->session();
        $token = $session->get('document_access_token');
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->put('document_access_token', $token);
        }

        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    public function getPublicTopics($courseId)
    {
        $topics = Topic::where('course_id', $courseId)->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('subject_id')->orWhereHas('subject', fn ($subject) => $subject->where('status', 'approved')))
            ->orderBy('sort_order')->get(['id', 'title', 'description', 'sort_order']);
        
        return response()->json([
            'success' => true,
            'topics' => $topics
        ]);
    }

    public function getProgress(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $courseId = $request->query('course_id');

        if ($courseId && ! $user->hasActiveEnrollment((int) $courseId)) {
            return response()->json(['success' => false, 'message' => 'You are not enrolled in this course.'], 403);
        }

        $query = UserProgress::where('user_id', $user->id);
        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        $progressData = $query->get(['topic_id', 'course_id', 'max_unlocked_index']);

        $completedTopics = $progressData->pluck('topic_id')->toArray();
        $topicProgressMap = $progressData->pluck('max_unlocked_index', 'topic_id')->toArray();

        $totalTopics = Topic::where('status', 'approved')->count();
        if ($courseId) {
            $totalTopics = Topic::where('course_id', $courseId)->where('status', 'approved')->count();
        }

        $completedCount = count($completedTopics);
        $progressPercentage = $totalTopics > 0 ? (int) round(($completedCount / $totalTopics) * 100) : 0;

        $certExists = false;
        if ($courseId) {
            $certExists = Certificate::where('user_id', $user->id)->where('course_id', $courseId)->exists();
        } else {
            $certExists = Certificate::where('user_id', $user->id)->exists();
        }

        return response()->json([
            'success' => true,
            'completedTopics' => $completedTopics,
            'topicProgressMap' => $topicProgressMap,
            'progressPercentage' => $progressPercentage,
            'modulesCompletedCount' => $completedCount,
            'lastTopicStarted' => $user->last_topic_id,
            'hasCertificate' => $certExists,
            'hasPassedMidterm' => $courseId ? QuizAttempt::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where('assessment_type', 'midterm')
                ->where('passed', true)
                ->exists() : false,
        ]);
    }

    public function startTopic(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
        ]);

        $topicId = $request->input('topic_id');
        $topic = Topic::findOrFail($topicId);
        if (! $user->hasActiveEnrollment((int) $topic->course_id)) {
            return response()->json(['success' => false, 'message' => 'You are not enrolled in this course.'], 403);
        }
        $user->last_topic_id = $topicId;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Topic started.',
            'lastTopicStarted' => $topicId,
        ]);
    }

    public function unlockProgress(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'item_type' => 'required|in:doc,video,zoom,assessment,none',
        ]);

        $topic = Topic::where('status', 'approved')->findOrFail($request->integer('topic_id'));
        if (! $user->hasActiveEnrollment((int) $topic->course_id)) {
            return response()->json(['success' => false, 'message' => 'You are not enrolled in this course.'], 403);
        }

        $items = $this->topicLearningItems($topic);
        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'This topic has no learning content.'], 422);
        }

        $progress = DB::transaction(function () use ($user, $topic, $items, $request) {
            $progress = UserProgress::firstOrCreate(
                ['user_id' => $user->id, 'topic_id' => $topic->id],
                ['course_id' => $topic->course_id, 'max_unlocked_index' => 0]
            );
            $progress = UserProgress::whereKey($progress->id)->lockForUpdate()->firstOrFail();
            $currentIndex = (int) $progress->max_unlocked_index;
            $expected = $items->get($currentIndex);

            if (! $expected) {
                return $progress;
            }

            if ((int) $expected['subtopic']->id !== $request->integer('subtopic_id') || $expected['type'] !== $request->input('item_type')) {
                abort(409, 'Only the next required learning item can be completed.');
            }

            if ($expected['type'] === 'assessment') {
                $batchId = CourseEnrollment::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->whereHas('batch', fn ($query) => $query->where('course_id', $topic->course_id))
                    ->value('batch_id');
                $hasScoredSubmission = QuizAttempt::where('user_id', $user->id)
                    ->where('course_id', $topic->course_id)
                    ->where('batch_id', $batchId)
                    ->where('subtopic_id', $expected['subtopic']->id)
                    ->whereNotNull('points_earned')
                    ->whereNotNull('points_possible')
                    ->exists();
                abort_unless($hasScoredSubmission, 422, 'A saved and scored assessment submission is required.');
            }

            $progress->max_unlocked_index = $currentIndex + 1;
            if ($progress->max_unlocked_index >= $items->count()) {
                $progress->completed_at ??= now();
            }
            $progress->save();

            return $progress;
        });

        return response()->json([
            'success' => true,
            'max_unlocked_index' => $progress->max_unlocked_index
        ]);
    }

    private function topicLearningItems(Topic $topic)
    {
        return $topic->subtopics()
            ->where('status', 'approved')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->flatMap(function (Subtopic $subtopic) {
                if ($subtopic->content_type === 'mock_exam') return [];
                if ($subtopic->content_type === 'zoom_link') return [['subtopic' => $subtopic, 'type' => 'zoom']];
                if ($subtopic->content_type !== 'subtopic') {
                    return [['subtopic' => $subtopic, 'type' => 'assessment']];
                }

                $items = [];
                if (filled($subtopic->documentation_path)) $items[] = ['subtopic' => $subtopic, 'type' => 'doc'];
                if (filled($subtopic->video_url) || filled($subtopic->video_path)) $items[] = ['subtopic' => $subtopic, 'type' => 'video'];
                if ($items === []) $items[] = ['subtopic' => $subtopic, 'type' => 'none'];
                return $items;
            })
            ->values();
    }

    public function submitQuiz(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'answers' => 'required|array',
        ]);

        $topicId = $request->input('topic_id');
        $topic = Topic::findOrFail($topicId);
        if (! $user->hasActiveEnrollment((int) $topic->course_id)) {
            return response()->json(['success' => false, 'message' => 'You are not enrolled in this course.'], 403);
        }
        $questionIds = session()->pull("topic_quiz_{$user->id}_{$topic->id}", []);
        $questionsById = $topic->quizQuestions()
            ->where('question_type', 'quiz')
            ->where('status', 'approved')
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');
        $questions = collect($questionIds)->map(fn ($id) => $questionsById->get($id))->filter()->values();

        if (empty($questionIds) || $questions->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active randomized quiz attempt was found. Please reopen the assessment.'], 422);
        }

        $submittedAnswers = $request->input('answers', []);
        $score = 0;
        $pointsEarned = 0.0;
        $pointsPossible = 0.0;
        $incorrectQuestions = [];
        $reviewQuestions = [];

        foreach ($questions as $index => $question) {
            $submitted = $submittedAnswers[$index] ?? null;
            $grade = $question->gradeAnswer($submitted);
            $pointsEarned += $grade['earned'];
            $pointsPossible += $grade['possible'];
            $reviewItem = ['question'=>$question->question,'imageUrl'=>$question->image_path ? asset('storage/'.$question->image_path) : null,'learnerAnswer'=>$question->formatAnswerForReview($submitted),'correctAnswer'=>$question->correctAnswerForReview(),'rationale'=>$question->rationale ?: 'No rationale was provided.','correct'=>(bool)$grade['correct']];
            $reviewQuestions[] = $reviewItem;
            if ($grade['correct']) $score++; else $incorrectQuestions[] = $reviewItem;
        }

        $total = $questions->count();
        $passingRatio = ((float) ($topic->quiz_passing_percentage ?? 80)) / 100;
        $passed = $pointsPossible > 0 && ($pointsEarned / $pointsPossible) >= $passingRatio;

        QuizAttempt::create([
            'user_id' => $user->id,
            'course_id' => $topic->course_id,
            'batch_id' => CourseEnrollment::where('user_id', $user->id)->whereHas('batch', fn ($query) => $query->where('course_id', $topic->course_id))->where('status', 'active')->value('batch_id'),
            'topic_id' => $topicId,
            'assessment_type' => 'quiz',
            'score' => $score,
            'total' => $total,
            'points_earned' => $pointsEarned,
            'points_possible' => $pointsPossible,
            'passed' => $passed,
            'review_data' => $reviewQuestions,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'Topic Quiz Completed',
            'description' => 'Scored ' . $score . '/' . $total . ' in ' . $topic->title . ' quiz. Status: ' . ($passed ? 'Passed' : 'Failed'),
            'ip_address' => $request->ip()
        ]);

        if ($passed) {
            UserProgress::firstOrCreate([
                'user_id' => $user->id,
                'topic_id' => $topicId
            ], ['course_id' => $topic->course_id]);
        }

        $progress = UserProgress::where('user_id', $user->id)->where('course_id', $topic->course_id)->pluck('topic_id')->toArray();
        $totalTopics = Topic::where('course_id', $topic->course_id)->where('status', 'approved')->count();
        $completedCount = count($progress);

        return response()->json([
            'success' => true,
            'message' => $passed ? 'Topic completed!' : 'Quiz completed.',
            'passed' => $passed,
            'score' => $pointsEarned,
            'total' => $pointsPossible,
            'completedTopics' => $progress,
            'progressPercentage' => $totalTopics > 0 ? (int) round(($completedCount / $totalTopics) * 100) : 0,
            'modulesCompletedCount' => $completedCount,
            'hasCertificate' => Certificate::where('user_id', $user->id)->where('course_id', $topic->course_id)->exists(),
            'questions' => $reviewQuestions,
            'incorrectQuestions' => $incorrectQuestions,
        ]);
    }
}
