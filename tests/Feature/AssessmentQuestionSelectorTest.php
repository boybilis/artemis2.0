<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Services\AssessmentQuestionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentQuestionSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_limit_is_balanced_by_category_and_fills_category_shortages(): void
    {
        $course = Course::create(['title'=>'NCLEX']);
        foreach (['easy'=>12,'average'=>12,'difficult'=>12] as $category => $count) {
            for ($index = 1; $index <= $count; $index++) QuizQuestion::create([
                'course_id'=>$course->id,'question_type'=>'final','response_type'=>'single','category'=>$category,
                'question'=>"{$category} {$index}",'options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'status'=>'approved',
            ]);
        }
        $selector = app(AssessmentQuestionSelector::class);
        $selected = $selector->select(QuizQuestion::where('course_id',$course->id), 30);
        $this->assertCount(30, $selected);
        $this->assertSame(['average'=>10,'difficult'=>10,'easy'=>10], $selected->countBy('category')->sortKeys()->all());

        QuizQuestion::where('course_id',$course->id)->where('category','difficult')->delete();
        $backfilled = $selector->select(QuizQuestion::where('course_id',$course->id), 20);
        $this->assertCount(20, $backfilled);
        $this->assertSame(0, $backfilled->where('category','difficult')->count());
    }
}
