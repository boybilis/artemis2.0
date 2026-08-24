<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEnrollmentAssessmentResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_view_top_twenty_course_mock_exam_rankings_by_each_learners_best_score(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::create(['title'=>'DOH-HAAD','approval_status'=>'approved','is_published'=>true]);
        $learners = User::factory()->count(22)->create(['role' => 'student']);

        foreach ($learners as $index => $learner) {
            CourseEnrollment::create(['user_id'=>$learner->id,'course_id'=>$course->id,'status'=>'active','enrolled_at'=>now()]);
            QuizAttempt::create(['user_id'=>$learner->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>$index + 1,'total'=>25,'passed'=>$index >= 19]);
        }
        QuizAttempt::create(['user_id'=>$learners->first()->id,'course_id'=>$course->id,'assessment_type'=>'final','score'=>25,'total'=>25,'passed'=>true]);

        $response = $this->actingAs($instructor)->getJson("/admin/content/courses/{$course->id}/rankings")
            ->assertOk()
            ->assertJsonCount(20, 'rankings')
            ->assertJsonPath('rankings.0.rank', 1)
            ->assertJsonPath('rankings.0.userId', $learners->first()->id)
            ->assertJsonPath('rankings.0.percentage', 100);

        $this->assertSame(20, count($response->json('rankings')));
    }

    public function test_instructor_can_list_enrolled_students_and_reset_only_the_selected_assessment(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title'=>'DOH-HAAD','approval_status'=>'approved','is_published'=>true]);
        CourseEnrollment::create(['user_id'=>$learner->id,'course_id'=>$course->id,'status'=>'active','enrolled_at'=>now()]);
        $subject = Subject::create(['course_id'=>$course->id,'subject_code'=>'MED','title'=>'Medical Nursing','status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Cardiology','status'=>'approved']);
        $postTest = Subtopic::create(['topic_id'=>$topic->id,'content_type'=>'post_test','title'=>'Post-Test','status'=>'approved']);
        QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$topic->id,'subtopic_id'=>$postTest->id,'question_type'=>'post_test','response_type'=>'single','question'=>'Question','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'status'=>'approved']);
        $selected = QuizAttempt::create(['user_id'=>$learner->id,'course_id'=>$course->id,'topic_id'=>$topic->id,'subtopic_id'=>$postTest->id,'assessment_type'=>'post_test','score'=>0,'total'=>1,'passed'=>false]);
        $other = QuizAttempt::create(['user_id'=>$learner->id,'course_id'=>$course->id,'topic_id'=>null,'assessment_type'=>'final','score'=>0,'total'=>1,'passed'=>false]);

        $this->actingAs($instructor)->getJson("/admin/content/courses/{$course->id}/enrollments")
            ->assertOk()->assertJsonPath('students.0.id', $learner->id)
            ->assertJsonPath('canUnenroll', false)
            ->assertJsonPath('subjects.0.id', $subject->id)
            ->assertJsonPath('assessments.0.subjectId', $subject->id);

        $this->postJson("/admin/content/courses/{$course->id}/assessment-attempts/reset", [
            'user_id' => $learner->id,
            'assessment' => 'subtopic:' . $postTest->id,
        ])->assertOk()->assertJsonPath('deletedAttempts', 1);

        $this->assertDatabaseMissing('quiz_attempts', ['id'=>$selected->id]);
        $this->assertDatabaseHas('quiz_attempts', ['id'=>$other->id]);

        $this->post("/admin/content/courses/{$course->id}/mock-exam/settings", [
            'mock_exam_passing_percentage' => 75,
            'mock_exam_maximum_attempts' => 3,
        ])->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id'=>$course->id,
            'mock_exam_passing_percentage'=>75,
            'mock_exam_maximum_attempts'=>3,
        ]);

        $this->post("/admin/content/courses/{$course->id}/assessments/pass-rule", [
            'assessment_scope'=>'subtopic',
            'assessment_id'=>$postTest->id,
            'passing_percentage'=>70,
        ])->assertRedirect();
        $this->assertDatabaseHas('subtopics', ['id'=>$postTest->id,'passing_percentage'=>70]);

        $this->postJson("/admin/content/courses/{$course->id}/enrollments/{$learner->id}/unenroll")->assertForbidden();
        $admin = User::factory()->create(['role'=>'admin','is_admin'=>true]);
        $this->actingAs($admin)->postJson("/admin/content/courses/{$course->id}/enrollments/{$learner->id}/unenroll")
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('course_enrollments', ['course_id'=>$course->id,'user_id'=>$learner->id]);
        $this->assertDatabaseHas('quiz_attempts', ['id'=>$other->id]);
    }
}
