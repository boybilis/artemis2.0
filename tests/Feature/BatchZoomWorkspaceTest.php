<?php

namespace Tests\Feature;

use App\Models\{BatchZoomSession, Course, CourseBatch, CourseEnrollment, Subject, Topic, Subtopic, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchZoomWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_sessions_are_workspace_only_and_isolated_from_other_batches(): void
    {
        $admin=User::factory()->create(['role'=>'assistant_admin','is_admin'=>false]);
        $learner=User::factory()->create();
        $course=Course::create(['title'=>'Nursing','is_published'=>true,'approval_status'=>'approved']);
        $subject=Subject::create(['course_id'=>$course->id,'title'=>'Fundamentals','subject_code'=>'N-01','status'=>'approved']);
        $topic=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Lecture Videos','status'=>'approved','sort_order'=>1]);
        $batches=collect(['A','B'])->map(function ($name) use ($course,$learner) {
            $batch=CourseBatch::create(['course_id'=>$course->id,'name'=>$name,'code'=>$name,'status'=>'open','price'=>100]);
            $batch->courses()->sync([$course->id]);
            CourseEnrollment::create(['user_id'=>$learner->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addMonth()]);
            return $batch;
        });
        $payload=['title'=>'Batch A Session','zoom_url'=>'https://zoom.us/j/111','starts_at'=>now()->addDay()->format('Y-m-d H:i:s'),'status'=>'scheduled','course_id'=>$course->id,'subject_id'=>$subject->id,'topic_id'=>null];
        $this->actingAs($admin)->getJson('/admin/classes/batches/'.$batches[0]->id.'/zoom-sessions')->assertOk()->assertJsonPath('courses.0.subjects.0.id',$subject->id);
        $id=$this->actingAs($admin)->postJson('/admin/classes/batches/'.$batches[0]->id.'/zoom-sessions',$payload)->assertOk()->json('session.id');
        $this->assertDatabaseCount('topics',1);
        $this->assertDatabaseCount('subtopics',0);
        $workspace=$this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/topics?batch_id='.$batches[0]->id)->assertOk();
        $zoom=collect($workspace->json('topics'))->firstWhere('title','Zoom Links');
        $this->assertTrue($zoom['isBatchZoomTopic']);
        $this->assertSame('Batch A Session',$zoom['subtopics'][0]['title']);
        $this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/topics?batch_id='.$batches[1]->id)->assertOk()->assertDontSee('Batch A Session');
        $payload['topic_id']=$topic->id;
        $payload['title']='Updated Batch A Session';
        $payload['zoom_url']='https://zoom.us/j/222';
        $this->actingAs($admin)->putJson('/admin/classes/batches/'.$batches[0]->id.'/zoom-sessions/'.$id,$payload)->assertOk();
        $live=$this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/zoom-sessions?batch_id='.$batches[0]->id)->assertOk();
        $live->assertJsonPath('sessions.0.zoom_url','https://zoom.us/j/222')->assertJsonPath('workspaceTopics.0.subtopics.0.title','Updated Batch A Session');
        $this->assertStringContainsString('no-store',$live->headers->get('Cache-Control'));
        $this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/zoom-sessions?batch_id='.$batches[1]->id)->assertOk()->assertJsonCount(0,'sessions')->assertJsonCount(0,'workspaceTopics');
        $workspace=$this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/topics?batch_id='.$batches[0]->id)->assertOk();
        $this->assertCount(2,$workspace->json('topics'));
        $this->assertSame('Zoom Links',$workspace->json('topics.1.title'));
        $this->assertSame('Updated Batch A Session',$workspace->json('topics.1.subtopics.0.title'));
        $payload['status']='cancelled';
        $this->actingAs($admin)->putJson('/admin/classes/batches/'.$batches[0]->id.'/zoom-sessions/'.$id,$payload)->assertOk();
        $this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/topics?batch_id='.$batches[0]->id)->assertOk()->assertDontSee('Batch A Session');
        $this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/zoom-sessions?batch_id='.$batches[0]->id)->assertOk()->assertJsonCount(0,'sessions')->assertJsonCount(0,'workspaceTopics');
        $this->assertDatabaseCount('topics',1);
        $this->assertDatabaseCount('subtopics',0);
        $this->assertSame('Lecture Videos',$topic->fresh()->title);
        $outsider=User::factory()->create();
        $this->actingAs($outsider)->getJson('/api/courses/'.$course->id.'/zoom-sessions?batch_id='.$batches[0]->id)->assertNotFound();
    }

    public function test_placement_cannot_cross_course_subject_or_batch_boundaries(): void
    {
        $admin=User::factory()->create(['role'=>'admin','is_admin'=>true]);
        $course=Course::create(['title'=>'A','is_published'=>true,'approval_status'=>'approved']);
        $other=Course::create(['title'=>'B','is_published'=>true,'approval_status'=>'approved']);
        $subject=Subject::create(['course_id'=>$other->id,'title'=>'Other Subject','status'=>'approved']);
        $topic=Topic::create(['course_id'=>$other->id,'subject_id'=>$subject->id,'title'=>'Other Topic','status'=>'approved']);
        $batch=CourseBatch::create(['course_id'=>$course->id,'name'=>'A','code'=>'A','status'=>'open','price'=>100]);
        $batch->courses()->sync([$course->id]);
        $payload=['title'=>'Bad Placement','zoom_url'=>'https://zoom.us/j/111','starts_at'=>now()->addDay()->format('Y-m-d H:i:s'),'status'=>'scheduled','course_id'=>$other->id,'subject_id'=>$subject->id,'topic_id'=>$topic->id];
        $this->actingAs($admin)->postJson('/admin/classes/batches/'.$batch->id.'/zoom-sessions',$payload)->assertUnprocessable();
        $payload['course_id']=$course->id;
        $this->actingAs($admin)->postJson('/admin/classes/batches/'.$batch->id.'/zoom-sessions',$payload)->assertUnprocessable();
        $this->assertDatabaseCount('batch_zoom_sessions',0);
    }

    public function test_all_batch_sessions_including_unassigned_legacy_sessions_share_one_zoom_topic(): void
    {
        $learner=User::factory()->create();
        $course=Course::create(['title'=>'Nursing','is_published'=>true,'approval_status'=>'approved']);
        $subject=Subject::create(['course_id'=>$course->id,'title'=>'Nursing Subject','status'=>'approved']);
        $batch=CourseBatch::create(['course_id'=>$course->id,'name'=>'Main Batch','code'=>'MAIN','status'=>'open','price'=>100]);
        $batch->courses()->sync([$course->id]);
        CourseEnrollment::create(['user_id'=>$learner->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addMonth()]);
        $base=['zoom_url'=>'https://zoom.us/j/111','starts_at'=>now()->addDay(),'status'=>'scheduled'];
        $first=$batch->zoomSessions()->create($base+['title'=>'Assigned session','course_id'=>$course->id,'subject_id'=>$subject->id]);
        $second=$batch->zoomSessions()->create($base+['title'=>'Older unassigned session']);
        $batch->zoomSessions()->create(array_merge($base,['title'=>'Cancelled session','status'=>'cancelled']));
        $other=CourseBatch::create(['course_id'=>$course->id,'name'=>'Other Batch','code'=>'OTHER','status'=>'open','price'=>100]);
        $other->courses()->sync([$course->id]);
        $other->zoomSessions()->create($base+['title'=>'Private other batch session']);
        foreach (['topics','zoom-sessions'] as $endpoint) {
            $data=$this->actingAs($learner)->getJson('/api/courses/'.$course->id.'/'.$endpoint.'?batch_id='.$batch->id)->assertOk();
            $entries=$data->json($endpoint==='topics'?'topics':'workspaceTopics');
            $this->assertCount(1,$entries);
            $this->assertTrue($entries[0]['isBatchWideZoomTopic']);
            $this->assertSame('Zoom Links',$entries[0]['title']);
            $this->assertCount(2,$entries[0]['subtopics']);
            $this->assertEqualsCanonicalizing([-$first->id,-$second->id],array_column($entries[0]['subtopics'],'id'));
            $data->assertDontSee('Cancelled session')->assertDontSee('Private other batch session');
        }
        $this->assertDatabaseCount('topics',0);
        $this->assertDatabaseCount('subtopics',0);
    }
}
