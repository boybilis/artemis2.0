<?php

namespace App\Services;

use App\Models\CourseBatch;
use Illuminate\Support\Collection;

class BatchZoomWorkspace
{
    // Overlay batch sessions on the response only. Never write master topics/subtopics.
    public function apply(Collection $topics, CourseBatch $batch, int $courseId, Collection $subjectIds): Collection
    {
        $topics = $topics->values();
        $sessions = $batch->zoomSessions()->where('course_id',$courseId)->whereIn('subject_id',$subjectIds)->where('status','scheduled')->orderBy('id')->get();
        foreach ($sessions as $session) {
            $index = $session->topic_id ? $topics->search(fn ($topic)=>(int)$topic['id']===(int)$session->topic_id && (int)$topic['subjectId']===(int)$session->subject_id) : false;
            if ($index === false) $index = $topics->search(fn ($topic)=>(int)$topic['subjectId']===(int)$session->subject_id && strcasecmp(trim($topic['title']),'Zoom Links')===0);
            if ($index === false) {
                $topics->push(['id'=>-$session->subject_id,'subjectId'=>$session->subject_id,'title'=>'Zoom Links','isBatchZoomTopic'=>true,'isPolicyTopic'=>false,'isPreTestTopic'=>false,'isLocked'=>false,'sort_order'=>$topics->count()+1,'subtopics'=>[],'quiz'=>[],'videos'=>[],'videoUrl'=>null,'documentationPath'=>null]);
                $index = $topics->count()-1;
            }
            $topic = $topics[$index];
            $topic['subtopics'] = collect($topic['subtopics'])->push([
                'id'=>-$session->id,'title'=>$session->title,'contentType'=>'zoom_link','isBatchZoom'=>true,
                'zoomUrl'=>$session->zoom_url,'zoomDescription'=>$session->description,
                'zoomStartsAt'=>$session->starts_at?->toIso8601String(),'zoomEndsAt'=>$session->ends_at?->toIso8601String(),
                'zoomRecordingUrl'=>$session->recording_url,'videoUrl'=>null,'videoUploadUrl'=>null,'documentationPath'=>null,'attemptsUsed'=>0,
            ])->values();
            $topics[$index] = $topic;
        }
        return $topics;
    }
}
