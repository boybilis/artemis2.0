<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Subject;
use App\Services\SubjectPolicy;

return new class extends Migration {
    public function up(): void
    {
        Subject::orderBy('id')->chunkById(100, function ($subjects) {
            foreach ($subjects as $subject) app(SubjectPolicy::class)->ensureDefault($subject);
        });
    }

    public function down(): void
    {
        // Preserve agreement content on rollback: it may already have learner progress.
    }
};
