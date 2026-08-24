<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Topic;
use App\Models\QuizQuestion;

class NewCoursesSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Course 2: HTML Fundamentals ─────────────────────────
        $html = Course::create([
            'title' => 'HTML Fundamentals',
            'description' => 'Master the building blocks of the web with HTML.',
            'is_published' => true,
        ]);

        $htmlTopics = [
            [
                'title' => 'Introduction to HTML & Document Structure',
                'sort_order' => 1,
                'quiz' => [
                    ['question' => 'What does HTML stand for?', 'options' => json_encode(['Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language']), 'answer' => 0],
                    ['question' => 'Which tag is used to define the document type in HTML5?', 'options' => json_encode(['<html>', '<!DOCTYPE html>', '<head>', '<meta>']), 'answer' => 1],
                    ['question' => 'Which element contains metadata about an HTML document?', 'options' => json_encode(['<body>', '<footer>', '<head>', '<main>']), 'answer' => 2],
                ],
            ],
            [
                'title' => 'Text Elements & Formatting',
                'sort_order' => 2,
                'quiz' => [
                    ['question' => 'Which tag defines the largest heading in HTML?', 'options' => json_encode(['<h6>', '<heading>', '<h1>', '<header>']), 'answer' => 2],
                    ['question' => 'Which tag is used to make text bold without semantic importance?', 'options' => json_encode(['<strong>', '<b>', '<em>', '<bold>']), 'answer' => 1],
                    ['question' => 'What does the <em> tag do?', 'options' => json_encode(['Makes text bold', 'Emphasizes text (italic)', 'Underlines text', 'Strikes through text']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Links, Images & Media',
                'sort_order' => 3,
                'quiz' => [
                    ['question' => 'Which attribute specifies the URL of a link?', 'options' => json_encode(['src', 'href', 'link', 'url']), 'answer' => 1],
                    ['question' => 'Which tag is used to embed an image?', 'options' => json_encode(['<picture>', '<image>', '<img>', '<media>']), 'answer' => 2],
                    ['question' => 'What attribute provides alternative text for an image?', 'options' => json_encode(['title', 'alt', 'description', 'caption']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Forms & Input Elements',
                'sort_order' => 4,
                'quiz' => [
                    ['question' => 'Which tag is used to create an HTML form?', 'options' => json_encode(['<input>', '<form>', '<fieldset>', '<submit>']), 'answer' => 1],
                    ['question' => 'Which input type creates a password field?', 'options' => json_encode(['type="text"', 'type="hidden"', 'type="password"', 'type="secure"']), 'answer' => 2],
                    ['question' => 'Which attribute specifies where form data is sent?', 'options' => json_encode(['method', 'action', 'target', 'enctype']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Tables & Lists',
                'sort_order' => 5,
                'quiz' => [
                    ['question' => 'Which tag defines a table row?', 'options' => json_encode(['<td>', '<tr>', '<th>', '<table>']), 'answer' => 1],
                    ['question' => 'Which tag creates an unordered list?', 'options' => json_encode(['<ol>', '<li>', '<ul>', '<list>']), 'answer' => 2],
                    ['question' => 'What is the correct tag for a list item?', 'options' => json_encode(['<item>', '<list>', '<il>', '<li>']), 'answer' => 3],
                ],
            ],
            [
                'title' => 'Semantic HTML & Accessibility',
                'sort_order' => 6,
                'quiz' => [
                    ['question' => 'Which tag represents a navigation section?', 'options' => json_encode(['<navigate>', '<nav>', '<menu>', '<links>']), 'answer' => 1],
                    ['question' => 'What does the <article> tag represent?', 'options' => json_encode(['A sidebar', 'A self-contained composition', 'A navigation link', 'A footer section']), 'answer' => 1],
                    ['question' => 'Which attribute improves accessibility for screen readers?', 'options' => json_encode(['class', 'id', 'aria-label', 'data-info']), 'answer' => 2],
                ],
            ],
        ];

        foreach ($htmlTopics as $topicData) {
            $topic = Topic::create([
                'title' => $topicData['title'],
                'sort_order' => $topicData['sort_order'],
                'course_id' => $html->id,
                'status' => 'approved',
            ]);

        }

        // ─── Course 3: JavaScript Basics ─────────────────────────
        $js = Course::create([
            'title' => 'JavaScript Basics',
            'description' => 'Learn the fundamentals of JavaScript programming.',
            'is_published' => true,
        ]);

        $jsTopics = [
            [
                'title' => 'Variables, Data Types & Operators',
                'sort_order' => 1,
                'quiz' => [
                    ['question' => 'Which keyword declares a block-scoped variable in JavaScript?', 'options' => json_encode(['var', 'let', 'const', 'Both let and const']), 'answer' => 3],
                    ['question' => 'What is the result of typeof null?', 'options' => json_encode(['"null"', '"undefined"', '"object"', '"boolean"']), 'answer' => 2],
                    ['question' => 'Which operator checks for strict equality?', 'options' => json_encode(['==', '===', '!=', '=>']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Control Flow & Loops',
                'sort_order' => 2,
                'quiz' => [
                    ['question' => 'Which loop guarantees at least one execution?', 'options' => json_encode(['for', 'while', 'do...while', 'for...in']), 'answer' => 2],
                    ['question' => 'What does the "break" statement do inside a loop?', 'options' => json_encode(['Skips the current iteration', 'Exits the loop entirely', 'Restarts the loop', 'Pauses the loop']), 'answer' => 1],
                    ['question' => 'Which statement is used for multi-way branching?', 'options' => json_encode(['if...else', 'switch', 'for', 'try...catch']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Functions & Scope',
                'sort_order' => 3,
                'quiz' => [
                    ['question' => 'What is hoisting in JavaScript?', 'options' => json_encode(['Moving variables to global scope', 'Moving declarations to the top of their scope', 'Removing unused variables', 'Compiling code before execution']), 'answer' => 1],
                    ['question' => 'Which syntax defines an arrow function?', 'options' => json_encode(['function() {}', '() => {}', 'func() {}', 'lambda() {}']), 'answer' => 1],
                    ['question' => 'What is a closure?', 'options' => json_encode(['A function that has no return value', 'A function with access to its outer scope variables', 'A self-invoking function', 'A function that runs only once']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'DOM Manipulation',
                'sort_order' => 4,
                'quiz' => [
                    ['question' => 'What does DOM stand for?', 'options' => json_encode(['Document Object Model', 'Data Object Management', 'Digital Output Mode', 'Document Oriented Middleware']), 'answer' => 0],
                    ['question' => 'Which method selects an element by its ID?', 'options' => json_encode(['querySelector()', 'getElementsByClassName()', 'getElementById()', 'getElement()']), 'answer' => 2],
                    ['question' => 'How do you change the text content of an element?', 'options' => json_encode(['element.text', 'element.textContent', 'element.value', 'element.setText()']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Events & Event Handling',
                'sort_order' => 5,
                'quiz' => [
                    ['question' => 'Which method adds an event listener to an element?', 'options' => json_encode(['element.on()', 'element.listen()', 'element.addEventListener()', 'element.attachEvent()']), 'answer' => 2],
                    ['question' => 'What does event.preventDefault() do?', 'options' => json_encode(['Stops event bubbling', 'Prevents the default browser action', 'Removes the event listener', 'Cancels the event entirely']), 'answer' => 1],
                    ['question' => 'What is event bubbling?', 'options' => json_encode(['Events fire from parent to child', 'Events fire from child to parent', 'Events fire only on the target', 'Events fire in random order']), 'answer' => 1],
                ],
            ],
            [
                'title' => 'Arrays, Objects & JSON',
                'sort_order' => 6,
                'quiz' => [
                    ['question' => 'Which method adds an element to the end of an array?', 'options' => json_encode(['unshift()', 'push()', 'append()', 'add()']), 'answer' => 1],
                    ['question' => 'How do you parse a JSON string into a JavaScript object?', 'options' => json_encode(['JSON.stringify()', 'JSON.parse()', 'JSON.decode()', 'JSON.toObject()']), 'answer' => 1],
                    ['question' => 'Which method creates a new array from calling a function on every element?', 'options' => json_encode(['forEach()', 'filter()', 'map()', 'reduce()']), 'answer' => 2],
                ],
            ],
        ];

        foreach ($jsTopics as $topicData) {
            $topic = Topic::create([
                'title' => $topicData['title'],
                'sort_order' => $topicData['sort_order'],
                'course_id' => $js->id,
                'status' => 'approved',
            ]);

        }

        $this->command->info('✅ 2 new courses seeded: HTML Fundamentals & JavaScript Basics');
    }
}
