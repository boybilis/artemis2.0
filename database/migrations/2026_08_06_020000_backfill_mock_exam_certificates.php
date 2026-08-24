<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $passedMockExams = DB::table('quiz_attempts')
            ->where('assessment_type', 'final')
            ->where('passed', true)
            ->whereNotNull('course_id')
            ->orderBy('id')
            ->get()
            ->unique(fn ($attempt) => $attempt->user_id . ':' . $attempt->course_id);

        $serial = DB::table('certificates')->count() + 1;
        foreach ($passedMockExams as $attempt) {
            $exists = DB::table('certificates')->where('user_id', $attempt->user_id)
                ->where('course_id', $attempt->course_id)->exists();
            if ($exists) continue;

            do {
                $code = 'ARTEMIS-CERT-' . date('Y') . '-' . str_pad((string) $serial++, 4, '0', STR_PAD_LEFT);
            } while (DB::table('certificates')->where('code', $code)->exists());

            DB::table('certificates')->insert([
                'user_id' => $attempt->user_id,
                'course_id' => $attempt->course_id,
                'code' => $code,
                'issued_at' => $attempt->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Issued credentials are intentionally preserved.
    }
};
