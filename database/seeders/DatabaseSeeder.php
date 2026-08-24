<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Topic;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\Voucher;
use App\Models\Certificate;
use App\Models\QuizAttempt;
use App\Models\UserProgress;
use App\Models\AuditLog;
use App\Models\Announcement;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ─── 1. SEED USERS ──────────────────────────────────────────
        // Seed Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@artemis.test',
            'password' => Hash::make('admin123'),
            'phone' => '+63 912 345 6789',
            'birthdate' => '01/01/1990',
            'affiliation_type' => 'company',
            'affiliation_name' => 'Artemis 2.0 Team',
            'is_admin' => true,
            'is_active' => true,
        ]);

        // Seed Sample Learners
        $maria = User::create([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'password' => Hash::make('password'),
            'phone' => '+63 917 888 1234',
            'birthdate' => '05/12/2000',
            'affiliation_type' => 'school',
            'affiliation_name' => 'University of Manila',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $john = User::create([
            'name' => 'John Reyes',
            'first_name' => 'John',
            'last_name' => 'Reyes',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '+63 918 777 5678',
            'birthdate' => '10/24/1995',
            'affiliation_type' => 'company',
            'affiliation_name' => 'Acme Corporation',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $rina = User::create([
            'name' => 'Rina Cruz',
            'first_name' => 'Rina',
            'last_name' => 'Cruz',
            'email' => 'rina@example.com',
            'password' => Hash::make('password'),
            'phone' => '+63 920 666 4321',
            'birthdate' => '08/15/2002',
            'affiliation_type' => 'school',
            'affiliation_name' => 'State College',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $alex = User::create([
            'name' => 'Alex Tan',
            'first_name' => 'Alex',
            'last_name' => 'Tan',
            'email' => 'alex@example.com',
            'password' => Hash::make('password'),
            'phone' => '+63 905 555 9876',
            'birthdate' => '12/01/1998',
            'affiliation_type' => 'company',
            'affiliation_name' => 'Design Studio PH',
            'is_admin' => false,
            'is_active' => false, // Inactive user
        ]);


        // ─── 2. SEED COURSE TOPICS, LESSONS, AND QUIZZES ─────────────
        $topicsData = [
            [
                'title' => 'CSS Introduction',
                'lessons' => [
                    [
                        'title' => 'CSS Home & Introduction',
                        'video_url' => 'https://www.youtube.com/embed/1Rs2ND1ryYc',
                        'notes' => 'CSS stands for Cascading Style Sheets. It describes how HTML elements are to be displayed on screen, paper, or in other media. CSS saves a lot of work. It can control the layout of multiple web pages all at once.'
                    ],
                    [
                        'title' => 'CSS Syntax & Selectors',
                        'video_url' => 'https://www.youtube.com/embed/l1mER1ZzY1Y',
                        'notes' => 'A CSS rule-set consists of a selector and a declaration block. The selector points to the HTML element you want to style. The declaration block contains one or more declarations separated by semicolons. Each declaration includes a CSS property name and a value, separated by a colon.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'What does CSS stand for?',
                        'options' => ['Cascading Style Sheets', 'Creative Style System', 'Computer Style Sheets', 'Colorful Style Sheets'],
                        'answer' => 0
                    ],
                    [
                        'question' => 'Which HTML tag is used to define an internal style sheet?',
                        'options' => ['<script>', '<css>', '<style>', '<design>'],
                        'answer' => 2
                    ]
                ]
            ],
            [
                'title' => 'CSS Syntax Deep Dive',
                'lessons' => [
                    [
                        'title' => 'Comments & Selectors',
                        'video_url' => 'https://www.youtube.com/embed/yfoY53QXEnI',
                        'notes' => 'CSS comments are not displayed in the browser, but they can help document your source code. Comments are placed inside the <style> element, and start with /* and end with */. Selectors are used to target specific HTML elements for styling.'
                    ],
                    [
                        'title' => 'Combinators & Pseudo-elements',
                        'video_url' => 'https://www.youtube.com/embed/mHAt-vYvFfM',
                        'notes' => 'A CSS combinator is something that explains the relationship between selectors. Pseudo-elements are used to style specified parts of an element, like the first letter/line, or inserting content before/after.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'How do you insert a comment in a CSS file?',
                        'options' => ['// this is a comment', '/* this is a comment */', '\' this is a comment', '// this is a comment //'],
                        'answer' => 1
                    ],
                    [
                        'question' => 'Which selector is used to style an element with a specific ID?',
                        'options' => ['.id', '#id', '*id', 'id='],
                        'answer' => 1
                    ]
                ]
            ],
            [
                'title' => 'CSS Colors',
                'lessons' => [
                    [
                        'title' => 'Colors, RGB, HEX, HSL',
                        'video_url' => 'https://www.youtube.com/embed/fD2Zp4baS24',
                        'notes' => 'CSS colors can be specified using predefined color names, or RGB, HEX, HSL, RGBA, HSLA values. RGB is Red, Green, Blue. HEX is Hexadecimal code. HSL stands for Hue, Saturation, Lightness.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which property is used to change the background color?',
                        'options' => ['color', 'bgcolor', 'background-color', 'surface-color'],
                        'answer' => 2
                    ],
                    [
                        'question' => 'How do you write \'Hello World\' in an HSL color format?',
                        'options' => ['hsl(0, 100%, 50%)', 'rgb(255, 0, 0)', '#FF0000', 'red'],
                        'answer' => 0
                    ]
                ]
            ],
            [
                'title' => 'CSS Backgrounds',
                'lessons' => [
                    [
                        'title' => 'Background Color & Images',
                        'video_url' => 'https://www.youtube.com/embed/yVIsP-O0n1M',
                        'notes' => 'The CSS background properties are used to add background effects for elements. Properties include background-color, background-image, background-repeat, background-attachment, background-position, and background (shorthand).'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which property is used to set the background image of an element?',
                        'options' => ['background-image', 'image-background', 'bg-image', 'content-image'],
                        'answer' => 0
                    ]
                ]
            ],
            [
                'title' => 'CSS Borders',
                'lessons' => [
                    [
                        'title' => 'Borders & Rounded Corners',
                        'video_url' => 'https://www.youtube.com/embed/n4p_nC-pTTo',
                        'notes' => 'The CSS border properties allow you to specify the style, width, and color of an element\'s border. The border-radius property is used to add rounded borders to an element.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which property is used to change the border width?',
                        'options' => ['border-width', 'width-border', 'thickness', 'border-style'],
                        'answer' => 0
                    ]
                ]
            ],
            [
                'title' => 'CSS Margins & Box Model',
                'lessons' => [
                    [
                        'title' => 'Margins & Box Model',
                        'video_url' => 'https://www.youtube.com/embed/nSst4-WbEzU',
                        'notes' => 'The CSS margin properties are used to create space around elements, outside of any defined borders. The CSS box model is essentially a box that wraps around every HTML element. It consists of: margins, borders, padding, and the actual content.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'In the CSS box model, which one is the outermost layer?',
                        'options' => ['Padding', 'Border', 'Margin', 'Content'],
                        'answer' => 2
                    ]
                ]
            ],
            [
                'title' => 'CSS Padding & Outline',
                'lessons' => [
                    [
                        'title' => 'Padding & Outlines',
                        'video_url' => 'https://www.youtube.com/embed/1Rs2ND1ryYc',
                        'notes' => 'The CSS padding properties are used to generate space around an element\'s content, inside of any defined borders. An outline is a line drawn outside the element\'s border to make the element "stand out".'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which property is used to change the left padding of an element?',
                        'options' => ['padding-left', 'left-padding', 'padding: left', 'spacing-left'],
                        'answer' => 0
                    ]
                ]
            ],
            [
                'title' => 'CSS Text',
                'lessons' => [
                    [
                        'title' => 'Text Formatting & Alignment',
                        'video_url' => 'https://www.youtube.com/embed/K8I8lSAsa6I',
                        'notes' => 'CSS has a lot of properties for formatting text: color, text-align, text-decoration, text-transform, text-shadow, line-height, letter-spacing, and word-spacing.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which property is used to change the color of text?',
                        'options' => ['text-color', 'fgcolor', 'color', 'font-color'],
                        'answer' => 2
                    ]
                ]
            ],
            [
                'title' => 'CSS Fonts',
                'lessons' => [
                    [
                        'title' => 'Font Families & Styles',
                        'video_url' => 'https://www.youtube.com/embed/hOshmK6CscA',
                        'notes' => 'Choosing the right font has a huge impact on how a website is experienced. CSS font properties define the font family, boldness, size, and the style of a text.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'Which CSS property controls the text size?',
                        'options' => ['font-style', 'text-size', 'font-size', 'text-style'],
                        'answer' => 2
                    ]
                ]
            ],
            [
                'title' => 'CSS Links, Lists & Tables',
                'lessons' => [
                    [
                        'title' => 'Links, Lists & Tables',
                        'video_url' => 'https://www.youtube.com/embed/cy9Hh6VvXN4',
                        'notes' => 'Links can be styled with any CSS property (e.g. color, font-family, background). Lists can have different list item markers, images, or backgrounds. Tables can be styled with borders, text alignment, padding, and hover transitions.'
                    ]
                ],
                'quizzes' => [
                    [
                        'question' => 'How do you remove the underline from all hyperlinks?',
                        'options' => ['a {text-decoration:none;}', 'a {underline:none;}', 'a {decoration:no-underline;}', 'a {text-style:none;}'],
                        'answer' => 0
                    ]
                ]
            ]
        ];

        $dbTopics = [];
        foreach ($topicsData as $idx => $t) {
            $topic = Topic::create([
                'title' => $t['title'],
                'sort_order' => $idx + 1
            ]);
            $dbTopics[] = $topic;

            foreach ($t['lessons'] as $lIdx => $l) {
                Lesson::create([
                    'topic_id' => $topic->id,
                    'title' => $l['title'],
                    'video_url' => $l['video_url'],
                    'notes' => $l['notes'],
                    'sort_order' => $lIdx + 1
                ]);
            }

        }

        // ─── 3. SEED FINAL EXAM QUESTIONS ───────────────────────────
        $finalExamQuestions = [
            [
                'question' => 'What is the correct CSS syntax?',
                'options' => ['body {color: black;}', '{body;color:black;}', 'body:color=black;', '{body:color=black;}'],
                'answer' => 0
            ],
            [
                'question' => 'How do you select an element with id \'demo\'?',
                'options' => ['.demo', '#demo', '*demo', 'demo'],
                'answer' => 1
            ],
            [
                'question' => 'How do you select elements with class name \'test\'?',
                'options' => ['*test', '#test', '.test', 'test'],
                'answer' => 2
            ],
            [
                'question' => 'How do you display hyperlinks without an underline?',
                'options' => ['a {decoration:no-underline;}', 'a {text-decoration:none;}', 'a {text-decoration:no-underline;}', 'a {underline:none;}'],
                'answer' => 1
            ],
            [
                'question' => 'Which property is used to change the left margin of an element?',
                'options' => ['margin-left', 'padding-left', 'indent', 'left-margin'],
                'answer' => 0
            ]
        ];

        // ─── 4. SEED PROGRESS & QUIZ ATTEMPTS FOR SAMPLE LEARNERS ───
        // A. Maria Santos (100% Progress, Certificated)
        foreach ($dbTopics as $topic) {
            UserProgress::create([
                'user_id' => $maria->id,
                'topic_id' => $topic->id,
                'completed_at' => Carbon::now()->subDays(5)
            ]);

            QuizAttempt::create([
                'user_id' => $maria->id,
                'topic_id' => $topic->id,
                'score' => count($topic->quizQuestions),
                'total' => count($topic->quizQuestions),
                'passed' => true,
                'created_at' => Carbon::now()->subDays(5)
            ]);
        }

        // Voucher purchased & used
        $mariaVoucher = Voucher::create([
            'code' => 'CSSM-MA74-P9L0',
            'price' => 299.00,
            'used' => true,
            'used_by' => $maria->id,
            'used_at' => Carbon::now()->subHours(12)
        ]);

        // Passed Final Exam
        QuizAttempt::create([
            'user_id' => $maria->id,
            'topic_id' => null,
            'score' => 5,
            'total' => 5,
            'passed' => true,
            'created_at' => Carbon::now()->subMinutes(12)
        ]);

        // Issue Certificate
        Certificate::create([
            'user_id' => $maria->id,
            'code' => 'LC-CERT-2026-0388',
            'issued_at' => Carbon::now()->subMinutes(12)
        ]);


        // B. John Reyes (80% Progress, 8 topics completed, 1 Voucher purchased but unused)
        for ($i = 0; $i < 8; $i++) {
            $topic = $dbTopics[$i];
            UserProgress::create([
                'user_id' => $john->id,
                'topic_id' => $topic->id,
                'completed_at' => Carbon::now()->subDays(2)
            ]);

            QuizAttempt::create([
                'user_id' => $john->id,
                'topic_id' => $topic->id,
                'score' => count($topic->quizQuestions),
                'total' => count($topic->quizQuestions),
                'passed' => true,
                'created_at' => Carbon::now()->subDays(2)
            ]);
        }

        // Voucher purchased, but still unused
        $johnVoucher = Voucher::create([
            'code' => 'CSSM-X8Q2-LP9A',
            'price' => 299.00,
            'used' => false,
            'used_by' => $john->id,
            'used_at' => null
        ]);


        // C. Rina Cruz (40% Progress, 4 topics completed)
        for ($i = 0; $i < 4; $i++) {
            $topic = $dbTopics[$i];
            UserProgress::create([
                'user_id' => $rina->id,
                'topic_id' => $topic->id,
                'completed_at' => Carbon::now()->subHours(5)
            ]);

            QuizAttempt::create([
                'user_id' => $rina->id,
                'topic_id' => $topic->id,
                'score' => count($topic->quizQuestions),
                'total' => count($topic->quizQuestions),
                'passed' => true,
                'created_at' => Carbon::now()->subHours(5)
            ]);
        }


        // D. Alex Tan (15% Progress, 1 topic completed)
        $topic = $dbTopics[0];
        UserProgress::create([
            'user_id' => $alex->id,
            'topic_id' => $topic->id,
            'completed_at' => Carbon::now()->subDays(10)
        ]);

        QuizAttempt::create([
            'user_id' => $alex->id,
            'topic_id' => $topic->id,
            'score' => 1,
            'total' => 2,
            'passed' => false, // Scored 1/2, did not pass (needs 100%)
            'created_at' => Carbon::now()->subDays(10)
        ]);


        // ─── 5. SEED VOUCHERS SOLD (REVENUE STUFF) ───────────────────
        // Seed some additional sold vouchers (active or used)
        Voucher::create(['code' => 'CSSM-5Y2F-K7R9', 'price' => 299.00, 'used' => false, 'used_by' => null]);
        Voucher::create(['code' => 'CSSM-A8H1-Q3N2', 'price' => 299.00, 'used' => true, 'used_by' => $rina->id, 'used_at' => Carbon::now()->subHours(3)]);
        Voucher::create(['code' => 'CSSM-J8K9-M4P6', 'price' => 299.00, 'used' => false, 'used_by' => null]);


        // ─── 6. SEED SYSTEM TIMELINE & AUDIT LOGS ────────────────────
        AuditLog::create([
            'user_id' => $maria->id,
            'action' => 'Registration',
            'description' => 'Maria Santos created an account from University of Manila.',
            'ip_address' => '192.168.1.10'
        ]);

        AuditLog::create([
            'user_id' => $maria->id,
            'action' => 'Voucher Purchase',
            'description' => 'Purchased voucher code CSSM-MA74-P9L0 for ₱299.',
            'ip_address' => '192.168.1.10'
        ]);

        AuditLog::create([
            'user_id' => $maria->id,
            'action' => 'Voucher Redemption',
            'description' => 'Redeemed voucher CSSM-MA74-P9L0 to unlock the Final Certification Exam.',
            'ip_address' => '192.168.1.10'
        ]);

        AuditLog::create([
            'user_id' => $maria->id,
            'action' => 'Final Exam Completed',
            'description' => 'Passed the Final Certification Exam with a perfect score (5/5).',
            'ip_address' => '192.168.1.10'
        ]);

        AuditLog::create([
            'user_id' => $maria->id,
            'action' => 'Certificate Issued',
            'description' => 'Issued official certificate LC-CERT-2026-0388.',
            'ip_address' => '192.168.1.10'
        ]);

        AuditLog::create([
            'user_id' => $john->id,
            'action' => 'Registration',
            'description' => 'John Reyes created an account from Acme Corporation.',
            'ip_address' => '192.168.1.11'
        ]);

        AuditLog::create([
            'user_id' => $john->id,
            'action' => 'Voucher Purchase',
            'description' => 'Purchased voucher code CSSM-X8Q2-LP9A for ₱299.',
            'ip_address' => '192.168.1.11'
        ]);

        AuditLog::create([
            'user_id' => $rina->id,
            'action' => 'Registration',
            'description' => 'Rina Cruz created an account from State College.',
            'ip_address' => '192.168.1.12'
        ]);

        AuditLog::create([
            'user_id' => $rina->id,
            'action' => 'Topic Quiz Completed',
            'description' => 'Completed Topic 1 quiz and scored 2/2.',
            'ip_address' => '192.168.1.12'
        ]);


        // ─── 7. SEED ANNOUNCEMENTS ──────────────────────────────────
        Announcement::create([
            'title' => 'Artemis 2.0 Certification Launch!',
            'content' => 'Welcome to Artemis 2.0, a review and assessment platform for professional licensure, eligibility, and certification examinations.',
            'created_by' => $admin->id
        ]);
    }
}
