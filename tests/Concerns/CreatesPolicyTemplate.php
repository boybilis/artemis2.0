<?php

namespace Tests\Concerns;

use App\Models\{Course, Subject, Topic, Subtopic};

trait CreatesPolicyTemplate
{
    protected function createPolicyTemplate(): void
    {
        $course = Course::create(['title'=>'Agreement Templates']);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Agreement Template', 'status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Policy', 'status'=>'approved']);
        Subtopic::create(['topic_id'=>$topic->id, 'title'=>'End User Agreement', 'content_type'=>'subtopic',
            'documentation_path'=>'https://example.test/End-User-Agreement.pdf', 'documentation_filename'=>'End User Agreement.pdf', 'status'=>'approved']);
    }
}
