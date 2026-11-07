const fs=require('fs'),assert=require('assert'),vm=require('vm');
const js=fs.readFileSync('public/script.js','utf8');
assert(js.includes("$('modal-course-profile').dataset.courseFamily = courseCardFamily(profile)"));
const html=fs.readFileSync('public/index.html','utf8');
const hero=html.match(/<section class="course-overview-hero">([\s\S]*?)<\/section>/)[1];
for(const id of ['back-to-courses-btn','course-profile-btn']) {
    assert(hero.includes(`id="${id}"`));
    assert.equal(html.split(`id="${id}"`).length-1,1);
}
assert(hero.includes('course-overview-enrolled'));
assert(hero.includes('class="course-overview-titlebar"'));
assert(hero.includes('<div class="course-overview-body"><h2 id="course-details-title">'));
assert(hero.includes('<hr class="course-overview-divider">'));
for(const id of ['course-subscription-ends','course-topics-completed','course-overview-progress'])assert(hero.includes(`id="${id}"`));
assert(hero.includes('SUBSCRIPTION ENDS'));
assert(js.includes('const expiresAt = course.enrollment_expires_at || course.batch_ends_at;'));
const helper=js.slice(js.indexOf('function courseCardFamily('),js.indexOf('function renderDashboard()'));
const family=vm.runInNewContext(helper+';courseCardFamily');
for(const [title,expected] of [['NCLEX review','nclex'],['PNLE course','pnle'],['Prometric Complete','prometric'],['CSE review','cse'],['DOH HAAD','default']]) {
    assert.equal(family({title}),expected);
    assert.equal(family({title,batch_name:'November',batch_code:'Other'}),expected);
}
assert.equal(family({title:'Review',batch_code:'october-prometric-mwf'}),'prometric');
assert.equal(family({title:'NCLEX',batch_code:'PNLE'}),'nclex');
assert(js.includes('card.dataset.courseFamily = courseCardFamily(course)'));
assert(js.includes("$('course-details-area').dataset.courseFamily = courseCardFamily(course)"));
assert(js.includes('<div class="course-card-open-label"><span>${escapeHtml(course.title)}</span>'));
assert(!js.includes('<span>Open ${escapeHtml(course.title)}</span>'));
const css=fs.readFileSync('public/brand-palette.css','utf8');
assert(css.includes('#learner-sidebar-course-name'));
assert(css.includes('white-space:normal;overflow-wrap:anywhere;word-break:normal;'));
for(const name of ['nclex','pnle','prometric','cse'])assert(css.includes(`[data-course-family="${name}"]`));
for(const name of ['nclex','pnle','prometric','cse'])assert(css.includes(`#course-details-area[data-course-family="${name}"]`));
require('postcss').parse(css);
assert(css.includes('background:var(--course-card-color-dark);color:#fff;border:0;'));
assert(css.includes('background:#fff;border:1px solid #d5e1e9;color:var(--text);'));
assert(css.includes('border-color:var(--course-card-color);\n    border-top:3px solid var(--course-card-color);'));
assert(css.includes('.learner-subject-action .btn-primary:not(:hover) { background:var(--course-card-color);color:var(--course-footer-text); }'));
assert(css.includes('background:var(--course-card-color);color:var(--course-footer-text)'));
function luminance(hex){const rgb=hex.match(/\w\w/g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return rgb.reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);}
for(const [bg,fg] of [['11a7c0','102338'],['e6b628','102338'],['28945f','081d13'],['19344f','ffffff'],['184b7a','ffffff']]){
    const values=[luminance(bg),luminance(fg)].sort((a,b)=>b-a);
    assert((values[0]+.05)/(values[1]+.05)>=4.5,`${bg} footer text contrast`);
}
console.log('Course colors are stable by family, title takes priority, batch fallback is case-insensitive, and CSS parses.');
