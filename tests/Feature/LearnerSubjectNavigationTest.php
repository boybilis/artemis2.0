<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\QuizAttempt;
use App\Models\Certificate;
use App\Models\CourseBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnerSubjectNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_dashboard_course_data_includes_existing_certificates(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title'=>'DOH-HAAD','approval_status'=>'approved','is_published'=>true]);
        CourseEnrollment::create(['user_id'=>$learner->id,'course_id'=>$course->id,'status'=>'active','enrolled_at'=>now()]);
        Certificate::create(['user_id'=>$learner->id,'course_id'=>$course->id,'code'=>'ARTEMIS-CERT-2026-0001','issued_at'=>now()]);

        $response = $this->actingAs($learner)->getJson('/api/courses')->assertOk()
            ->assertJsonCount(1, 'certificates')
            ->assertJsonPath('certificates.0.courseId', $course->id)
            ->assertJsonPath('certificates.0.code', 'ARTEMIS-CERT-2026-0001');
        $courseData = collect($response->json('courses'))->firstWhere('id', $course->id);
        $this->assertTrue($courseData['has_certificate']);
        $this->assertSame('ARTEMIS-CERT-2026-0001', $courseData['certificate']['code']);
    }

    public function test_uploaded_video_uses_a_session_bound_temporary_url_instead_of_a_public_storage_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('subtopic-videos/lesson.mp4', 'test-video-content');
        $learner = User::factory()->create();
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'approved','is_published'=>true]);
        $batch = CourseBatch::create([
            'course_id'=>$course->id,
            'name'=>'NCLEX Batch 1',
            'code'=>'NCLEX-B1',
            'status'=>'open',
            'created_by'=>$learner->id,
        ]);
        CourseEnrollment::create(['user_id'=>$learner->id,'course_id'=>$course->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now()]);
        $subject = Subject::create(['course_id'=>$course->id,'subject_code'=>'N1','title'=>'Nursing','status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Safety','status'=>'approved']);
        \App\Models\Subtopic::create(['topic_id'=>$topic->id,'title'=>'Lesson Video','content_type'=>'subtopic','status'=>'approved','video_path'=>'subtopic-videos/lesson.mp4','video_filename'=>'lesson.mp4']);

        $response = $this->actingAs($learner)->getJson("/api/courses/{$course->id}/topics")->assertOk();
        $videoUrl = $response->json('topics.0.subtopics.0.videoUploadUrl');
        $this->assertStringContainsString('/api/learning/videos/', $videoUrl);
        $this->assertStringContainsString('signature=', $videoUrl);
        $this->assertStringNotContainsString('/storage/subtopic-videos/', $videoUrl);

        $parts = parse_url($videoUrl);
        $videoResponse = $this->get($parts['path'] . '?' . $parts['query'])->assertOk();
        $this->assertStringContainsString('private', $videoResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $videoResponse->headers->get('Cache-Control'));
        $this->withSession(['video_access_token' => 'a-different-browser-session'])
            ->get($parts['path'] . '?' . $parts['query'])->assertForbidden();
    }

    public function test_private_drive_video_hides_its_original_url_and_uses_an_artemis_signed_url(): void
    {
        config()->set('services.google_drive.streaming_enabled', true);
        config()->set('services.google_drive.folder_id', 'approved-folder-id');

        $learner = User::factory()->create();
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'approved','is_published'=>true]);
        $batch = CourseBatch::create([
            'course_id'=>$course->id,
            'name'=>'NCLEX Batch 1',
            'code'=>'NCLEX-B1',
            'status'=>'open',
            'created_by'=>$learner->id,
        ]);
        CourseEnrollment::create([
            'user_id'=>$learner->id,
            'course_id'=>$course->id,
            'batch_id'=>$batch->id,
            'status'=>'active',
            'enrolled_at'=>now(),
        ]);
        $subject = Subject::create(['course_id'=>$course->id,'subject_code'=>'N1','title'=>'Nursing','status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Policy','status'=>'approved']);
        $driveFileId = '1AbCdEfGhIjKlMnOpQrStUvWxYz';
        \App\Models\Subtopic::create([
            'topic_id'=>$topic->id,
            'title'=>'Private Video',
            'content_type'=>'subtopic',
            'status'=>'approved',
            'video_url'=>"https://drive.google.com/file/d/{$driveFileId}/view",
        ]);

        $response = $this->actingAs($learner)->getJson("/api/courses/{$course->id}/topics")->assertOk();
        $response->assertJsonPath('topics.0.subtopics.0.videoUrl', null);
        $protectedUrl = $response->json('topics.0.subtopics.0.videoUploadUrl');
        $this->assertStringContainsString('/api/learning/videos/', $protectedUrl);
        $this->assertStringContainsString('signature=', $protectedUrl);
        $this->assertStringNotContainsString('drive.google.com', $protectedUrl);
        $this->assertStringNotContainsString($driveFileId, $protectedUrl);
    }

    public function test_enrolled_learner_sees_their_mock_exam_rank_on_the_course_card_data(): void
    {
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'approved','is_published'=>true]);
        $learner = User::factory()->create();
        $higher = User::factory()->create();
        $lower = User::factory()->create();
        foreach ([$learner, $higher, $lower] as $user) {
            CourseEnrollment::create(['user_id'=>$user->id,'course_id'=>$course->id,'status'=>'active','enrolled_at'=>now()]);
        }
        QuizAttempt::create(['user_id'=>$higher->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>95,'total'=>100,'passed'=>true]);
        QuizAttempt::create(['user_id'=>$learner->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>80,'total'=>100,'passed'=>true]);
        QuizAttempt::create(['user_id'=>$learner->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>70,'total'=>100,'passed'=>false]);
        QuizAttempt::create(['user_id'=>$lower->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>60,'total'=>100,'passed'=>false]);

        $courseData = collect($this->actingAs($learner)->getJson('/api/courses')->assertOk()->json('courses'))->firstWhere('id', $course->id);
        $this->assertSame(2, $courseData['mock_exam_rank']);
        $this->assertSame(3, $courseData['mock_exam_ranked_count']);
    }

    public function test_enrolled_learner_receives_only_approved_subjects_with_progress(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'approved','is_published'=>true]);
        CourseEnrollment::create(['user_id'=>$user->id,'course_id'=>$course->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addMonth()]);
        $approved = Subject::create(['course_id'=>$course->id,'subject_code'=>'N1','title'=>'Nursing','status'=>'approved']);
        $pending = Subject::create(['course_id'=>$course->id,'subject_code'=>'N2','title'=>'Hidden','status'=>'pending']);
        $completed = Topic::create(['course_id'=>$course->id,'subject_id'=>$approved->id,'title'=>'Completed','status'=>'approved']);
        Topic::create(['course_id'=>$course->id,'subject_id'=>$approved->id,'title'=>'Next','status'=>'approved']);
        Topic::create(['course_id'=>$course->id,'subject_id'=>$pending->id,'title'=>'Hidden Topic','status'=>'approved']);
        UserProgress::create(['user_id'=>$user->id,'course_id'=>$course->id,'topic_id'=>$completed->id,'max_unlocked_index'=>1]);

        $this->actingAs($user)->getJson("/api/courses/{$course->id}/topics")
            ->assertOk()->assertJsonCount(1, 'subjects')->assertJsonPath('subjects.0.code', 'N1')
            ->assertJsonPath('subjects.0.completedTopics', 1)->assertJsonPath('subjects.0.topicCount', 2)
            ->assertJsonPath('subjects.0.progressPercentage', 50)->assertJsonCount(2, 'topics');
    }

    public function test_course_prices_use_the_learners_saved_pricing_region(): void
    {
        $course = Course::create(['title'=>'DOH-HAAD','price'=>1200,'usd_price'=>24.50,'approval_status'=>'approved','is_published'=>true,'available_until'=>now()->addMonth()]);
        $international = User::factory()->create(['country_code'=>'INTL']);
        $internationalResponse = $this->actingAs($international)->getJson('/api/courses')->assertOk();
        $internationalCourse = collect($internationalResponse->json('courses'))->firstWhere('id', $course->id);
        $this->assertSame(24.5, $internationalCourse['display_price']);
        $this->assertSame('USD', $internationalCourse['currency_code']);
        $this->assertEquals(1200.0, $internationalCourse['billing_price']);
        $this->assertSame('PHP', $internationalCourse['billing_currency_code']);

        $philippine = User::factory()->create(['country_code'=>'PH']);
        $philippineResponse = $this->actingAs($philippine)->getJson('/api/courses')->assertOk();
        $philippineCourse = collect($philippineResponse->json('courses'))->firstWhere('id', $course->id);
        $this->assertEquals(1200.0, $philippineCourse['display_price']);
        $this->assertSame('PHP', $philippineCourse['currency_code']);
        $this->assertEquals(1200.0, $philippineCourse['billing_price']);
        $this->assertSame('PHP', $philippineCourse['billing_currency_code']);
    }
}
