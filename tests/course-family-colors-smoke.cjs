const fs=require('fs'),assert=require('assert'),vm=require('vm');
const js=fs.readFileSync('public/script.js','utf8');
const helper=js.slice(js.indexOf('function courseCardFamily('),js.indexOf('function renderDashboard()'));
const family=vm.runInNewContext(helper+';courseCardFamily');
for(const [title,expected] of [['NCLEX review','nclex'],['PNLE course','pnle'],['Prometric Complete','prometric'],['CSE review','cse'],['DOH HAAD','default']]) {
    assert.equal(family({title}),expected);
    assert.equal(family({title,batch_name:'November',batch_code:'Other'}),expected);
}
assert.equal(family({title:'Review',batch_code:'october-prometric-mwf'}),'prometric');
assert.equal(family({title:'NCLEX',batch_code:'PNLE'}),'nclex');
assert(js.includes('card.dataset.courseFamily = courseCardFamily(course)'));
assert(js.includes('<div class="course-card-open-label"><span>${escapeHtml(course.title)}</span>'));
assert(!js.includes('<span>Open ${escapeHtml(course.title)}</span>'));
const css=fs.readFileSync('public/brand-palette.css','utf8');
for(const name of ['nclex','pnle','prometric','cse'])assert(css.includes(`[data-course-family="${name}"]`));
require('postcss').parse(css);
console.log('Course colors are stable by family, title takes priority, batch fallback is case-insensitive, and CSS parses.');
