<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Topic;
use App\Models\Subtopic;
use Illuminate\Support\Facades\Storage;

class DummyCourseSeeder extends Seeder
{
    public function run()
    {
        // 1. Wipe existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Subtopic::truncate();
        Topic::truncate();
        DB::table('quiz_questions')->truncate();
        
        // Also clear out user progress so things don't break with old IDs
        DB::table('user_progress')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Ensure documentation folder exists
        if (!Storage::disk('public')->exists('documentation')) {
            Storage::disk('public')->makeDirectory('documentation');
        }

        // 3. Create a tiny valid dummy PDF (base64 encoded minimal PDF)
        $minimalPdf = base64_decode("JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nDPUM1QwMLYEMvXMtAwNTC30DA0U9A31DXXM9EwNdQwMlIAiYQoGQCFDKAAUlgx8CmVuZHN0cmVhbQplbmRvYmoKCjMgMCBvYmoKMzcKZW5kb2JqCgo0IDAgb2JqCjw8L1R5cGUvUGFnZS9NZWRpYUJveFswIDAgNTk1IDg0Ml0vUGFyZW50IDUgMCBSL1Jlc291cmNlczw8L0ZvbnQ8PC9GMSA2IDAgUj4+Pj4vQ29udGVudHMgMiAwIFI+PgplbmRvYmoKCjUgMCBvYmoKPDwvVHlwZS9QYWdlcy9LaWRzWzQgMCBSXS9Db3VudCAxPj4KZW5kb2JqCgo2IDAgb2JqCjw8L1R5cGUvRm9udC9TdWJ0eXBlL1R5cGUxL0Jhc2VGb250L0hlbHZldGljYT4+CmVuZG9iagoKNyAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgNSAwIFI+PgplbmRvYmoKCjEgMCBvYmoKPDwvUHJvZHVjZXIoZHVtbXkpL0NyZWF0aW9uRGF0ZShEOjIwMjQwMTAxMDAwMDAwWik+PgplbmRvYmoKeHJlZgowIDgKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwNDM5IDAwMDAwIG4gCjAwMDAwMDAwMTkgMDAwMDAgbiAKMDAwMDAwMDEyOSAwMDAwMCBuIAowMDAwMDAwMTQ4IDAwMDAwIG4gCjAwMDAwMDAyNDUgMDAwMDAgbiAKMDAwMDAwMDMwMiAwMDAwMCBuIAowMDAwMDAwMzkwIDAwMDAwIG4gCnRyYWlsZXIKPDwvU2l6ZSA4L1Jvb3QgNyAwIFIvSW5mbyAxIDAgUj4+CnN0YXJ0eHJlZgo0OTQKJSVFT0YK");
        Storage::disk('public')->put('documentation/dummy.pdf', $minimalPdf);

        // Random CSS-related youtube videos
        $videos = [
            'https://www.youtube.com/watch?v=1Rs2ND1ryYc', // CSS Tutorial
            'https://www.youtube.com/watch?v=yfoY53QXEnI', // CSS Crash Course
            'https://www.youtube.com/watch?v=OXGznpKZ_sA', // CSS Flexbox
            'https://www.youtube.com/watch?v=jV8B24rSN5o', // CSS Grid
            'https://www.youtube.com/watch?v=zJSY8tbf_ys', // Frontend Web Dev
            'https://www.youtube.com/watch?v=mU6anWqZJcc', // Learn HTML & CSS
            'https://www.youtube.com/watch?v=G3e-cpL7ofc', // HTML CSS Full Course
            'https://www.youtube.com/watch?v=1PnVor36_40', // CSS Variables
        ];

        $topicsData = [
            ['title' => 'Introduction to CSS', 'desc' => 'Learn the basics of Cascading Style Sheets, including syntax, selectors, and how to include CSS in your HTML.'],
            ['title' => 'The Box Model', 'desc' => 'Understand the CSS box model: margins, borders, padding, and the actual content.'],
            ['title' => 'Typography & Colors', 'desc' => 'Styling text, choosing fonts, and working with color values (HEX, RGB, HSL) and backgrounds.'],
            ['title' => 'CSS Flexbox', 'desc' => 'A deep dive into the Flexible Box Layout module to create one-dimensional responsive layouts.'],
            ['title' => 'CSS Grid Layout', 'desc' => 'Mastering the two-dimensional layout system to build complex web interfaces.'],
            ['title' => 'Responsive Design & Media Queries', 'desc' => 'Making your websites adapt to different screen sizes and devices using media queries.'],
        ];

        foreach ($topicsData as $i => $data) {
            $topic = Topic::create([
                'title' => $data['title'],
                'description' => $data['desc'],
                'sort_order' => $i + 1,
                'status' => 'approved',
            ]);

            // Create 3 subtopics for each topic
            for ($j = 1; $j <= 3; $j++) {
                $videoUrl = $videos[array_rand($videos)];
                Subtopic::create([
                    'topic_id' => $topic->id,
                    'title' => $data['title'] . ' - Part ' . $j,
                    'sort_order' => $j,
                    'video_url' => $videoUrl,
                    'documentation_path' => '/storage/documentation/dummy.pdf',
                    'documentation_filename' => 'dummy_slides_part_'.$j.'.pdf',
                    'status' => 'approved',
                ]);
            }

            // Create some dummy quizzes for the topic so "Take Quiz" works
            for ($q = 0; $q < 2; $q++) {
                DB::table('quiz_questions')->insert([
                    'topic_id' => $topic->id,
                    'question' => 'Sample Question ' . ($q + 1) . ' for ' . $data['title'] . '?',
                    'options' => json_encode(['Option A', 'Option B', 'Option C', 'Option D']),
                    'answer' => 0, // First option is correct
                    'status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
