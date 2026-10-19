<?php
namespace Tests\Feature;

use App\Models\{TestBankQuiz,TestBankQuizAttempt};
use App\Services\TestBankReadiness;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TestBankReadinessTest extends TestCase
{
    private int $nextId=1;
    private function quiz(string $type,string $phase,array $extra=[]): TestBankQuiz {
        return (new TestBankQuiz())->forceFill($extra+['id'=>$this->nextId++,'quiz_type'=>$type,'test_type'=>$phase,'title'=>$phase.' '.$this->nextId,
            'subject_ids'=>[1],'item_count'=>$phase==='mastery' ? 20 : 10,'description'=>$type==='subject' ? 'V2 one-time subject test.' : null]);
    }
    private function attempt(TestBankQuiz $quiz,int $score,bool $cancelled=false): TestBankQuizAttempt {
        return (new TestBankQuizAttempt(['test_bank_quiz_id'=>$quiz->id,'score'=>$score,'total'=>100,'review_data'=>$cancelled ? ['cancelled'=>true] : []]))->setRelation('quiz',$quiz);
    }
    private function fixture(int $score): array {
        $warm=$this->quiz('subject','warm_up');$mastery=$this->quiz('subject','mastery');
        $sim1=$this->quiz('premade','simulation',['title'=>'Simulation Test 1']);
        $sim2=$this->quiz('premade','simulation',['title'=>'Simulation Test 2']);
        $attempts=collect([$this->attempt($warm,$score),$this->attempt($mastery,$score),$this->attempt($sim1,$score),$this->attempt($sim2,$score)]);
        foreach(['warm_up'=>10,'mastery'=>5] as $phase=>$count) {
            for($i=0;$i<$count;$i++)$attempts->push($this->attempt($this->quiz('learner',$phase),$score));
        }
        return [collect([['id'=>1]]),$attempts,collect([$sim1,$sim2]),$warm];
    }
    public function test_sixty_percent_scores_with_full_coverage_give_sixty_readiness(): void {
        [$subjects,$attempts,$premades]=$this->fixture(60);
        $result=TestBankReadiness::calculate($subjects,$attempts,$premades);
        $this->assertEquals(60,$result['percentage']);
        $this->assertEquals(30,$result['categories']['premade']['contribution']);
        $this->assertEquals(18,$result['categories']['custom']['contribution']);
        $this->assertEquals(12,$result['categories']['simulation']['contribution']);
    }
    public function test_premades_use_highest_score_not_average_of_three_attempts(): void {
        [$subjects,$attempts,$premades,$warm]=$this->fixture(100);
        $attempts->push($this->attempt($warm,30),$this->attempt($warm,60));
        $result=TestBankReadiness::calculate($subjects,$attempts,$premades);
        $this->assertEquals(100,$result['percentage']);
        $this->assertEquals(50,$result['categories']['premade']['contribution']);
    }
    public function test_custom_average_includes_tests_beyond_ten_and_five_and_retakes(): void {
        [$subjects,$attempts,$premades]=$this->fixture(100);
        for($i=0;$i<5;$i++)$attempts->push($this->attempt($this->quiz('learner','warm_up'),0));
        $result=TestBankReadiness::calculate($subjects,$attempts,$premades);
        $this->assertEquals(75,$result['categories']['custom']['average']);
        $this->assertEquals(22.5,$result['categories']['custom']['contribution']);
        $this->assertEquals(92.5,$result['percentage']);
        $custom=$attempts->first(fn ($attempt)=>$attempt->quiz->quiz_type==='learner')->quiz;
        $attempts->push($this->attempt($custom,0));
        $result=TestBankReadiness::calculate($subjects,$attempts,$premades);
        $this->assertEquals(15,$result['categories']['custom']['completedWarmUp']);
        $this->assertEquals(21,$result['categories']['custom']['attempts']);
        $this->assertEquals(71.4,$result['categories']['custom']['average']);
    }
    public function test_missing_requirements_and_cancellations_do_not_earn_credit(): void {
        $quiz=$this->quiz('learner','warm_up');
        $attempts=collect([$this->attempt($quiz,100),$this->attempt($quiz,100),$this->attempt($this->quiz('learner','mastery'),100,true)]);
        $result=TestBankReadiness::calculate(collect([['id'=>1]]),$attempts,collect());
        $this->assertEquals(2,$result['percentage']);
        $this->assertEquals(1,$result['categories']['custom']['completedWarmUp']);
        $this->assertEquals(0,$result['categories']['custom']['completedMastery']);
        $this->assertEquals(0,$result['categories']['premade']['contribution']);
        $this->assertEquals(0,$result['categories']['simulation']['contribution']);
    }
    public function test_one_perfect_simulation_earns_ten_not_twenty_and_retakes_do_not_replace_second_test(): void {
        $sim=$this->quiz('premade','simulation');
        $result=TestBankReadiness::calculate(collect(),collect([$this->attempt($sim,100),$this->attempt($sim,100)]),collect([$sim]));
        $this->assertEquals(10,$result['percentage']);
        $this->assertEquals(1,$result['categories']['simulation']['completed']);
    }
}
