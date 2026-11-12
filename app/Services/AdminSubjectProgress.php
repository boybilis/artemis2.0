<?php

namespace App\Services;

use App\Models\{CourseBatch, QuizAttempt, Subject, Topic, User, UserProgress};
use Illuminate\Support\Facades\Storage;

class AdminSubjectProgress
{
    public function page(User $user, int $courseId, CourseBatch $batch)
    {
        $subjects = Subject::where('course_id', $courseId)->where('status', 'approved')
            ->tap(fn ($query) => $batch->filterSubjects($query))
            ->orderBy('sort_order')->orderBy('title')->orderBy('id')
            ->paginate(10, ['id', 'title'], 'subjects_page')->withQueryString();

        $topics = Topic::where('course_id', $courseId)->where('status', 'approved')
            ->whereIn('subject_id', $subjects->getCollection()->pluck('id'))
            ->with(['subtopics' => fn ($query) => $query->where('status', 'approved')->orderBy('id')])
            ->get(['id', 'subject_id', 'title']);
        $progress = UserProgress::where('user_id', $user->id)->where('course_id', $courseId)
            ->whereIn('topic_id', $topics->pluck('id'))->pluck('max_unlocked_index', 'topic_id');
        $unaccepted = app(SubjectPolicy::class)->unacceptedTopicIds($user->id, $courseId)->flip();
        $attempts = QuizAttempt::where('user_id', $user->id)->where('course_id', $courseId)->where('batch_id', $batch->id)
            ->whereIn('subtopic_id', $topics->flatMap->subtopics->pluck('id'))->latest('id')
            ->get(['id', 'subtopic_id', 'score', 'total', 'points_earned', 'points_possible', 'passed'])
            ->groupBy('subtopic_id');
        $topicsBySubject = $topics->groupBy('subject_id');
        $rules = Storage::disk('local')->json("course-gamification/{$courseId}.json") ?? [];
        $pointsEnabled = !empty($rules['enabled']) && !empty($rules['points_enabled']);

        return $subjects->through(function ($subject) use ($topicsBySubject, $progress, $unaccepted, $attempts, $rules, $pointsEnabled) {
            $total = $completed = $completedLearning = $passedAssessments = 0;
            $results = ['pre_test' => [], 'post_test' => []];
            foreach ($topicsBySubject->get($subject->id, collect()) as $topic) {
                $position = 0;
                foreach ($topic->subtopics as $item) {
                    $type = $item->content_type ?: 'subtopic';
                    if ($type === 'mock_exam') continue;
                    $learning = $type === 'subtopic';
                    $count = $learning ? max(1, (int) filled($item->documentation_path) + (int) (filled($item->video_url) || filled($item->video_path))) : 1;
                    $itemAttempts = $attempts->get($item->id, collect());
                    $done = $unaccepted->has($topic->id) ? 0 : max(0, min($count, (int) $progress->get($topic->id, 0) - $position));
                    if (!$learning && $type !== 'zoom_link' && $itemAttempts->isEmpty()) $done = 0;
                    $position += $count;
                    $total += $count;
                    $completed += $done;
                    if ($learning && (filled($item->instructions) || filled($item->documentation_path) || filled($item->video_url) || filled($item->video_path))) {
                        $completedLearning += $done;
                    }
                    if (!$learning && $type !== 'zoom_link' && $itemAttempts->contains(fn ($attempt) => $attempt->passed)) $passedAssessments++;
                    if (array_key_exists($type, $results)) {
                    $results[$type][] = ['title' => $item->title, 'attempts' => $itemAttempts];
                    }
                }
            }

            return [
                'id' => $subject->id, 'title' => $subject->title,
                'percentage' => $total ? (int) round(100 * $completed / $total) : 0,
                'results' => $results,
                'exp' => $pointsEnabled ? $completedLearning * (int) ($rules['points']['content'] ?? 0)
                    + $passedAssessments * (int) ($rules['points']['assessment'] ?? 0) : null,
            ];
        });
    }
}
