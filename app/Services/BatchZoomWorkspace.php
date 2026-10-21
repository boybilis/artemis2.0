<?php

namespace App\Services;

use App\Models\CourseBatch;
use Illuminate\Support\Collection;

class BatchZoomWorkspace
{
    // Overlay batch sessions on the response only. Never write master topics/subtopics.
    public function apply(Collection $topics, CourseBatch $batch): Collection
    {
        $topics = $topics->values();
        $sessions = $batch->zoomSessions()->where('status','scheduled')->orderBy('id')->get();
        if ($sessions->isEmpty()) return $topics;
        $topic = ['id'=>-$batch->id,'subjectId'=>null,'title'=>'Zoom Links','isBatchZoomTopic'=>true,'isBatchWideZoomTopic'=>true,'isPolicyTopic'=>false,'isPreTestTopic'=>false,'isLocked'=>false,'sort_order'=>$topics->count()+1,'subtopics'=>[],'quiz'=>[],'videos'=>[],'videoUrl'=>null,'documentationPath'=>null];
        foreach ($sessions as $session) {
            $topic['subtopics'] = collect($topic['subtopics'])->push([
                'id'=>-$session->id,'title'=>$session->title,'contentType'=>'zoom_link','isBatchZoom'=>true,
                'zoomUrl'=>$session->zoom_url,'zoomDescription'=>$session->description,
                'zoomStartsAt'=>$session->starts_at?->toIso8601String(),'zoomEndsAt'=>$session->ends_at?->toIso8601String(),
                'zoomRecordingUrl'=>$session->recording_url,'videoUrl'=>null,'videoUploadUrl'=>null,'documentationPath'=>null,'attemptsUsed'=>0,
            ])->values();
        }
        return $topics->push($topic);
    }
}
