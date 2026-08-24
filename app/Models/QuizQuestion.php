<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id', 'subtopic_id', 'question', 'rationale', 'image_path', 'image_filename', 'options', 'answer', 'correct_answers',
        'question_type', 'response_type', 'category', 'status', 'course_id',
        'response_config', 'maximum_points', 'scoring_method',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answers' => 'array',
            'response_config' => 'array',
            'maximum_points' => 'float',
        ];
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function subtopic()
    {
        return $this->belongsTo(Subtopic::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function learnerResponseConfig(): ?array
    {
        if (!in_array($this->response_type, ['grid', 'cloze', 'highlight'], true) || !is_array($this->response_config)) return null;
        $config = $this->response_config;
        if ($this->response_type === 'highlight') {
            foreach ($config['segments'] ?? [] as $index => $segment) {
                unset($segment['is_correct']);
                $config['segments'][$index] = $segment;
            }
            return $config;
        }
        if ($this->response_type === 'cloze') {
            foreach ($config['blanks'] ?? [] as $blankIndex => $blank) {
                foreach ($blank['options'] ?? [] as $optionIndex => $option) {
                    unset($option['is_correct']);
                    $config['blanks'][$blankIndex]['options'][$optionIndex] = $option;
                }
            }
            return $config;
        }
        foreach ($config['rows'] ?? [] as $rowIndex => $row) {
            foreach ($row['cells'] ?? [] as $cellIndex => $cell) {
                if (!in_array(($cell['type'] ?? null), ['dropdown', 'sata'], true)) continue;
                foreach ($cell['options'] ?? [] as $optionIndex => $option) {
                    unset($option['points'], $option['is_correct']);
                    $config['rows'][$rowIndex]['cells'][$cellIndex]['options'][$optionIndex] = $option;
                }
            }
        }
        unset($config['calculated_maximum_points']);
        return $config;
    }

    public function formatAnswerForReview(mixed $answer): string
    {
        if ($answer === null || $answer === '' || $answer === []) return 'No answer';
        if ($this->response_type === 'cloze') {
            $answers = is_array($answer) ? $answer : [];
            return collect($this->response_config['blanks'] ?? [])->map(function ($blank) use ($answers) {
                $value = $answers[$blank['key']] ?? null;
                $label = collect($blank['options'] ?? [])->firstWhere('value', $value)['label'] ?? 'No answer';
                return ($blank['label'] ?? $blank['key']) . ': ' . $label;
            })->join('; ') ?: 'No answer';
        }
        if ($this->response_type === 'highlight') {
            $selected = collect(is_array($answer) ? $answer : [$answer]);
            return collect($this->response_config['segments'] ?? [])->whereIn('key', $selected)->pluck('text')->join(' | ') ?: 'No answer';
        }
        if ($this->response_type !== 'grid') {
            $indexes = is_array($answer) ? $answer : [$answer];
            return collect($indexes)->map(fn ($index) => $this->options[(int) $index] ?? null)->filter()->join(', ') ?: 'No answer';
        }

        $answers = is_array($answer) ? $answer : [];
        return collect($this->response_config['rows'] ?? [])->flatMap(function ($row) use ($answers) {
            return collect($row['cells'] ?? [])->filter(fn ($cell) => in_array($cell['type'] ?? null, ['dropdown', 'sata'], true))->map(function ($cell) use ($row, $answers) {
                $key = ($row['key'] ?? '') . '.' . ($cell['column_key'] ?? '');
                $values = (array) ($answers[$key] ?? []);
                $labels = collect($cell['options'] ?? [])->whereIn('value', $values)->pluck('label')->join(', ');
                return ($row['label'] ?? $row['key'] ?? 'Row') . ' / ' . ($cell['column_label'] ?? $cell['column_key'] ?? 'Response') . ': ' . ($labels ?: 'No answer');
            });
        })->join('; ') ?: 'No answer';
    }

    public function correctAnswerForReview(): string
    {
        if ($this->response_type === 'cloze') {
            $correct = collect($this->response_config['blanks'] ?? [])->mapWithKeys(fn ($blank) => [$blank['key'] => collect($blank['options'] ?? [])->firstWhere('is_correct', true)['value'] ?? null])->all();
            return $this->formatAnswerForReview($correct);
        }
        if ($this->response_type === 'highlight') {
            return $this->formatAnswerForReview(collect($this->response_config['segments'] ?? [])->where('is_correct', true)->pluck('key')->all());
        }
        if ($this->response_type !== 'grid') return $this->formatAnswerForReview($this->correct_answers ?: [(int) $this->answer]);
        $correct = [];
        foreach ($this->response_config['rows'] ?? [] as $row) {
            foreach ($row['cells'] ?? [] as $cell) {
                if (!in_array($cell['type'] ?? null, ['dropdown', 'sata'], true)) continue;
                $key = ($row['key'] ?? '') . '.' . ($cell['column_key'] ?? '');
                $correct[$key] = collect($cell['options'] ?? [])->where('is_correct', true)->pluck('value')->all();
            }
        }
        return $this->formatAnswerForReview($correct);
    }

    public function gradeAnswer(mixed $submitted): array
    {
        $maximum = max(0.01, (float) ($this->maximum_points ?: 1));
        if ($this->response_type === 'cloze') {
            $submitted = is_array($submitted) ? $submitted : [];
            $blanks = collect($this->response_config['blanks'] ?? []);
            $correctCount = $blanks->filter(function ($blank) use ($submitted) {
                $correct = collect($blank['options'] ?? [])->firstWhere('is_correct', true)['value'] ?? null;
                return $correct !== null && ($submitted[$blank['key']] ?? null) === $correct;
            })->count();
            $allCorrect = $blanks->isNotEmpty() && $correctCount === $blanks->count();
            $earned = $this->scoring_method === 'partial_credit' && $blanks->count()
                ? ($correctCount / $blanks->count()) * $maximum
                : ($allCorrect ? $maximum : 0);
            return ['earned' => round($earned, 2), 'possible' => $maximum, 'correct' => $allCorrect];
        }
        if ($this->response_type === 'highlight') {
            $expected = collect($this->response_config['segments'] ?? [])->where('is_correct', true)->pluck('key')->sort()->values();
            $actual = collect(is_array($submitted) ? $submitted : [$submitted])->filter()->unique()->sort()->values();
            $correctSelected = $actual->intersect($expected)->count();
            $incorrectSelected = $actual->diff($expected)->count();
            $allCorrect = $actual->all() === $expected->all();
            $earned = $this->scoring_method === 'partial_credit' && $expected->count()
                ? max(0, ($correctSelected - $incorrectSelected) / $expected->count()) * $maximum
                : ($allCorrect ? $maximum : 0);
            return ['earned' => round($earned, 2), 'possible' => $maximum, 'correct' => $allCorrect];
        }
        if ($this->response_type !== 'grid') {
            $expected = collect($this->correct_answers ?: [(int) $this->answer])->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
            $actual = collect(is_array($submitted) ? $submitted : [$submitted])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
            $correct = $actual === $expected;
            return ['earned' => $correct ? $maximum : 0.0, 'possible' => $maximum, 'correct' => $correct];
        }

        $submitted = is_array($submitted) ? $submitted : [];
        $rawEarned = 0.0;
        $rawPossible = 0.0;
        $allCorrect = true;
        foreach ($this->response_config['rows'] ?? [] as $row) {
            foreach ($row['cells'] ?? [] as $cell) {
                if (!in_array(($cell['type'] ?? null), ['dropdown', 'sata'], true)) continue;
                $key = ($row['key'] ?? '') . '.' . ($cell['column_key'] ?? '');
                $values = ($cell['type'] ?? null) === 'sata' ? (array) ($submitted[$key] ?? []) : [$submitted[$key] ?? null];
                $options = collect($cell['options'] ?? []);
                $chosen = $options->whereIn('value', $values);
                $correctValues = $options->where('is_correct', true)->pluck('value')->sort()->values()->all();
                $actualValues = $chosen->pluck('value')->sort()->values()->all();
                $possible = $options->where('is_correct', true)->sum(fn ($option) => (float) ($option['points'] ?? 0));
                if (($cell['type'] ?? null) === 'dropdown') $possible = $options->max(fn ($option) => (float) ($option['points'] ?? 0)) ?: 0;
                $rawPossible += $possible;
                $rawEarned += min($possible, $chosen->sum(fn ($option) => (float) ($option['points'] ?? 0)));
                if ($actualValues !== $correctValues) $allCorrect = false;
            }
        }
        $earned = $rawPossible > 0 ? min($maximum, ($rawEarned / $rawPossible) * $maximum) : ($allCorrect ? $maximum : 0);
        if ($this->scoring_method === 'all_or_nothing') $earned = $allCorrect ? $maximum : 0;
        return ['earned' => round($earned, 2), 'possible' => $maximum, 'correct' => $allCorrect];
    }
}
