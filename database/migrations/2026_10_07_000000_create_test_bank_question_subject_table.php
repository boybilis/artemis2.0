<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_bank_question_subject')) {
            Schema::create('test_bank_question_subject', function (Blueprint $table) {
                $table->id();
                $table->foreignId('test_bank_question_id')->constrained('test_bank_questions')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['test_bank_question_id', 'subject_id'], 'tb_question_subject_unique');
                $table->index(['subject_id', 'test_bank_question_id'], 'tb_subject_question_index');
            });
        }

        if (Schema::hasTable('test_bank_questions')) {
            DB::table('test_bank_questions')
                ->select(['id', 'subject_id'])
                ->whereNotNull('subject_id')
                ->orderBy('id')
                ->chunkById(500, function ($questions) {
                    $now = now();
                    DB::table('test_bank_question_subject')->insertOrIgnore(
                        $questions->map(fn ($question) => [
                            'test_bank_question_id' => $question->id,
                            'subject_id' => $question->subject_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all()
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_bank_question_subject');
    }
};
