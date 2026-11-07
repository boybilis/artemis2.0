<?php

namespace App\Services;

use App\Models\{Subject, Topic, Subtopic, UserProgress};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectPolicy
{
    public function policy(?int $subjectId): ?Topic
    {
        if (!$subjectId) return null;
        return Topic::where('subject_id', $subjectId)->orderBy('sort_order')->orderBy('id')->get()
            ->first(fn ($topic) => $topic->isPolicyPrerequisite());
    }

    public function accepted(int $userId, ?int $subjectId): bool
    {
        return !$this->policy($subjectId) || DB::table('subject_policy_acceptances')
            ->where('user_id', $userId)->where('subject_id', $subjectId)->exists();
    }

    public function authorizeTopic(int $userId, Topic $topic): void
    {
        if (!$topic->subject_id) return;
        abort_unless($topic->subject?->status === 'approved', 404);
        abort_unless($topic->isPolicyPrerequisite() || $this->accepted($userId, $topic->subject_id),
            403, 'Read and accept this subject’s End User Agreement first.');
    }

    public function completed(int $userId, Topic $policy): bool
    {
        if ($policy->status !== 'approved') return false;
        $items = $policy->subtopics()->where('status', 'approved')->orderBy('id')->get();
        // All configured policy content must be completed, not just opened.
        $required = [];
        $index = 0;
        foreach ($items as $sub) {
            if ($sub->content_type === 'mock_exam') continue;
            if ($sub->content_type === 'pre_test') { $index++; continue; }
            $count = $sub->content_type === 'subtopic'
                ? (int) filled($sub->documentation_path) + (int) (filled($sub->video_url) || filled($sub->video_path)) : 1;
            if (!$count) { $index++; continue; }
            for ($i = 0; $i < $count; $i++) $required[] = $index++;
        }
        $unlocked = (int) UserProgress::where('user_id', $userId)->where('topic_id', $policy->id)->value('max_unlocked_index');
        return count($required) > 0 && collect($required)->every(fn ($itemIndex) => $itemIndex < $unlocked);
    }

    public function ensureDefault(Subject $subject): void
    {
        $policy = $this->policy($subject->id);
        if ($policy && $policy->subtopics()->whereNotNull('documentation_path')->where('documentation_path', '!=', '')->get()
            ->contains(fn ($sub) => preg_match('/end[\s_-]*user[\s_-]*agreement/i', $sub->title . ' ' . $sub->documentation_filename))) return;
        $templates = Subtopic::with('topic')->where('status', 'approved')->where('content_type', 'subtopic')
            ->whereNotNull('documentation_path')->where('documentation_path', '!=', '')
            ->whereHas('topic', fn ($q) => $q->where('status', 'approved'))->orderBy('id')->get()
            ->filter(fn ($sub) => $sub->topic->isPolicyPrerequisite()
                && preg_match('/end[\s_-]*user[\s_-]*agreement/i', $sub->title . ' ' . $sub->documentation_filename))
            ->sortBy(fn ($sub) => $sub->topic->course_id == $subject->course_id ? 0 : 1);
        $template = $templates->first(function ($sub) {
            if (preg_match('#^https?://#i', $sub->documentation_path)) return true;
            return Storage::disk('public')->exists(ltrim(str_replace('/storage/', '', $sub->documentation_path), '/'));
        });
        if (!$template) throw ValidationException::withMessages([
            'policy' => 'An approved End User Agreement PDF is required. Add it to an existing Policy section before creating subjects or running the policy backfill.',
        ]);
        $path = $template->documentation_path;
        if (!preg_match('#^https?://#i', $path)) {
            $source = ltrim(str_replace('/storage/', '', $path), '/');
            $copy = 'documentation/policies/' . Str::uuid() . '.' . (pathinfo($source, PATHINFO_EXTENSION) ?: 'pdf');
            if (!Storage::disk('public')->copy($source, $copy)) throw new \RuntimeException('The End User Agreement could not be copied.');
            $path = '/storage/' . $copy;
        }
        DB::transaction(function () use ($subject, $policy, $template, $path) {
            $policy ??= Topic::create(['course_id'=>$subject->course_id, 'subject_id'=>$subject->id,
                'title'=>'Policy', 'description'=>'End User Agreement', 'sort_order'=>0, 'status'=>'approved']);
            Subtopic::create(['topic_id'=>$policy->id, 'title'=>'End User Agreement', 'content_type'=>'subtopic',
                'documentation_path'=>$path, 'documentation_filename'=>$template->documentation_filename ?: 'End User Agreement.pdf',
                'sort_order'=>($policy->subtopics()->max('sort_order') ?? 0) + 1, 'status'=>'approved']);
        });
    }
}
