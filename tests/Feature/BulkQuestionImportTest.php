<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_bulk_import_multiple_choice_questions_as_pending(): void
    {
        $instructor = User::factory()->create(['role'=>'instructor', 'is_admin'=>false]);
        $course = Course::create(['title'=>'DOH-HAAD']);
        $subject = Subject::create(['course_id'=>$course->id, 'subject_code'=>'NURS-1', 'title'=>'Nursing', 'status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Cardiology', 'status'=>'approved']);
        $preTest = Subtopic::create(['topic_id'=>$topic->id, 'title'=>'Pre-Test', 'content_type'=>'pre_test', 'status'=>'approved']);
        $csv = implode("\n", [
            'question,option_a,option_b,option_c,option_d,option_e,option_f,option_g,option_h,correct_answer,category,rationale,maximum_points',
            '"Which pulse should be assessed before digoxin?","Apical","Radial","Pedal","Carotid",,,,,"A","average","Assess the apical pulse.","1"',
            '"Which finding requires follow-up?","Pulse 52","Pulse 78","BP 120/80","RR 18",,,,,"A","difficult","Bradycardia requires follow-up.","2"',
        ]);

        $this->actingAs($instructor)->post(route('admin.content.quizzes.bulk-import', $course), [
            'subject_id'=>$subject->id, 'question_type'=>'pre_test',
            'csv_file'=>UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertSame(2, QuizQuestion::count());
        $question = QuizQuestion::first();
        $this->assertSame('single', $question->response_type);
        $this->assertSame($preTest->id, $question->subtopic_id);
        $this->assertSame('pending', $question->status);
        $this->assertSame(['Apical','Radial','Pedal','Carotid'], $question->options);
        $this->assertSame([0], $question->correct_answers);
    }

    public function test_invalid_csv_row_imports_nothing(): void
    {
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $course = Course::create(['title'=>'DOH-HAAD']);
        $subject = Subject::create(['course_id'=>$course->id, 'subject_code'=>'NURS-1', 'title'=>'Nursing', 'status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Cardiology', 'status'=>'approved']);
        Subtopic::create(['topic_id'=>$topic->id, 'title'=>'Pre-Test', 'content_type'=>'pre_test', 'status'=>'approved']);
        $csv = "question,option_a,option_b,correct_answer\nInvalid question,Yes,No,Z";

        $this->actingAs($admin)->post(route('admin.content.quizzes.bulk-import', $course), [
            'subject_id'=>$subject->id, 'question_type'=>'pre_test',
            'csv_file'=>UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ])->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('quiz_questions', 0);
    }
}
