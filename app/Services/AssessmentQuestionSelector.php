<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class AssessmentQuestionSelector
{
    public function select(Builder|Relation $query, ?int $limit): Collection
    {
        if (! $limit) return (clone $query)->inRandomOrder()->get();

        $limit = max(1, $limit);
        $categories = ['easy', 'average', 'difficult'];
        $base = intdiv($limit, count($categories));
        $remainder = $limit % count($categories);
        $selected = collect();

        foreach ($categories as $index => $category) {
            $target = $base + ($index < $remainder ? 1 : 0);
            if ($target < 1) continue;
            $selected = $selected->concat((clone $query)->where('category', $category)->inRandomOrder()->limit($target)->get());
        }

        $missing = $limit - $selected->count();
        if ($missing > 0) {
            $selected = $selected->concat((clone $query)->whereNotIn('id', $selected->pluck('id'))->inRandomOrder()->limit($missing)->get());
        }

        return $selected->shuffle()->take($limit)->values();
    }
}
