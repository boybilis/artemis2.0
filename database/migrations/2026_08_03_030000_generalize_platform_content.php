<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        DB::table('announcements')
            ->where('content', 'like', '%full CSS course%')
            ->update([
                'content' => 'Welcome to Artemis 2.0, a review and assessment platform for professional licensure, eligibility, and certification examinations.',
            ]);
    }

    public function down(): void
    {
        // The original announcement may contain user-edited content, so it is not restored automatically.
    }
};
