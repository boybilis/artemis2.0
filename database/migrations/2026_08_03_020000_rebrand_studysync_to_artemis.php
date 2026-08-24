<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('affiliation_name', 'StudySync Team')
                ->update(['affiliation_name' => 'Artemis 2.0 Team']);
        }

        if (Schema::hasTable('announcements')) {
            DB::table('announcements')
                ->where('title', 'StudySync Certification v1.0 Launch!')
                ->update(['title' => 'Artemis 2.0 Certification Launch!']);

            DB::table('announcements')
                ->where('content', 'like', '%StudySync Certification platform%')
                ->update([
                    'content' => DB::raw("REPLACE(content, 'StudySync Certification platform', 'Artemis 2.0 Certification platform')"),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('affiliation_name', 'Artemis 2.0 Team')
                ->update(['affiliation_name' => 'StudySync Team']);
        }

        if (Schema::hasTable('announcements')) {
            DB::table('announcements')
                ->where('title', 'Artemis 2.0 Certification Launch!')
                ->update(['title' => 'StudySync Certification v1.0 Launch!']);

            DB::table('announcements')
                ->where('content', 'like', '%Artemis 2.0 Certification platform%')
                ->update([
                    'content' => DB::raw("REPLACE(content, 'Artemis 2.0 Certification platform', 'StudySync Certification platform')"),
                ]);
        }
    }
};
