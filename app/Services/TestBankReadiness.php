<?php
namespace App\Services;

use Illuminate\Support\Collection;

class TestBankReadiness
{
    private static function phase($quiz): string
    {
        return $quiz->test_type ?: (str_contains($quiz->title,'Mastery Test') || (int)$quiz->item_count === 20 ? 'mastery' : 'warm_up');
    }

    public static function calculate(Collection $subjects, Collection $attempts, Collection $premades): array
    {
        $completed=$attempts->filter(fn ($attempt)=>$attempt->total > 0 && !data_get($attempt->review_data,'cancelled',false) && $attempt->quiz);
        $percentage=fn ($attempt)=>max(0,min(100,$attempt->percentage()));
        $slots=[];
        foreach ($subjects as $subject) {
            foreach (['warm_up','mastery'] as $phase) $slots['subject:'.$subject['id'].':'.$phase]=collect();
        }
        foreach ($premades as $quiz) {
            if (in_array($quiz->test_type,['warm_up','mastery'],true)) $slots['quiz:'.$quiz->id]=collect();
        }
        foreach ($completed as $attempt) {
            $quiz=$attempt->quiz;
            $key=$quiz->quiz_type === 'subject' && $quiz->description === 'V2 one-time subject test.'
                ? 'subject:'.collect($quiz->subject_ids)->first().':'.self::phase($quiz)
                : ($quiz->quiz_type === 'premade' ? 'quiz:'.$quiz->id : null);
            if ($key && isset($slots[$key])) $slots[$key]->push($percentage($attempt));
        }
        $premadeAverage=count($slots) ? collect($slots)->sum(fn ($scores)=>$scores->isEmpty() ? 0 : $scores->max())/count($slots) : 0;
        $custom=$completed->filter(fn ($attempt)=>$attempt->quiz->quiz_type === 'learner');
        $warm=$custom->filter(fn ($attempt)=>self::phase($attempt->quiz)==='warm_up')->unique('test_bank_quiz_id')->count();
        $mastery=$custom->filter(fn ($attempt)=>self::phase($attempt->quiz)==='mastery')->unique('test_bank_quiz_id')->count();
        $customAverage=$custom->isEmpty() ? 0 : $custom->avg($percentage);
        $customCoverage=(min(10,$warm)+min(5,$mastery))/15;
        // Match the learner list's natural ordering; only the first two assigned simulations are required.
        $simulations=$premades->filter(fn ($quiz)=>$quiz->test_type === 'simulation')
            ->sort(fn ($a,$b)=>strnatcasecmp($a->title,$b->title) ?: $a->id <=> $b->id)->take(2);
        $simulationScores=$simulations->map(function ($quiz) use ($completed,$percentage) {
            $results=$completed->where('test_bank_quiz_id',$quiz->id);
            return ['average'=>$results->isEmpty() ? 0 : $results->avg($percentage),'completed'=>$results->isNotEmpty()];
        });
        $simulationAverage=$simulationScores->sum('average')/2;
        $parts=[
            'premade'=>['weight'=>50,'average'=>round($premadeAverage,1),'contribution'=>round($premadeAverage*.5,2),'completed'=>collect($slots)->filter(fn ($scores)=>$scores->isNotEmpty())->count(),'required'=>count($slots)],
            'custom'=>['weight'=>30,'average'=>round($customAverage,1),'contribution'=>round($customAverage*.3*$customCoverage,2),'completedWarmUp'=>$warm,'requiredWarmUp'=>10,'completedMastery'=>$mastery,'requiredMastery'=>5,'attempts'=>$custom->count()],
            'simulation'=>['weight'=>20,'average'=>round($simulationAverage,1),'contribution'=>round($simulationAverage*.2,2),'completed'=>$simulationScores->where('completed',true)->count(),'required'=>2],
        ];
        return ['percentage'=>round($premadeAverage*.5+$customAverage*.3*$customCoverage+$simulationAverage*.2,1),'categories'=>$parts];
    }
}
