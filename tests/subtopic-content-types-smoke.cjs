const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const js = fs.readFileSync('public/script.js', 'utf8');
const source = js.slice(js.indexOf('function getTopicFlatLearningItems('), js.indexOf('function isPolicyLearningTopic('));
const flatten = vm.runInNewContext(`${source}\ngetTopicFlatLearningItems`);
const items = flatten({subtopics:[
    {contentType:'subtopic', instructions:'First paragraph.\n\nSecond paragraph.'},
    {contentType:'subtopic', documentationPath:'lesson.pdf', videoUrl:'https://example.test/video'},
    {contentType:'pre_test', instructions:'Read before starting.'},
]});
assert.deepEqual(Array.from(items, item => item.type), ['text', 'doc', 'video', 'assessment']);
assert(js.includes("textContainer.textContent = item.type === 'text' ? item.sub.instructions : '';"));
const view = fs.readFileSync('resources/views/admin/content/topics.blade.php', 'utf8');
const options = view.match(/<select id="add_sub_type"[^>]*>([\s\S]*?)<\/select>/)[1];
assert.deepEqual(Array.from(options.matchAll(/<option value="([^"]+)"/g), match => match[1]),
    ['text', 'video', 'reading', 'pre_test', 'post_test', 'practice_test', 'mock_exam']);
