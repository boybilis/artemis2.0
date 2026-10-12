const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

function element(value = '') {
    return {value, dataset: {}, listeners: {}, classList: {toggle() {}, add() {}, remove() {}},
        addEventListener(type, listener) { this.listeners[type] = listener; },
        setAttribute() {}, scrollIntoView() {},
        set innerHTML(html) { this.html = html; const option = html.match(/<option value="([^"]*)"/); if (option) this.value = option[1]; },
        get innerHTML() { return this.html || ''; }};
}
const subject = element('mixed'), premade = element(), start = element(), choice = element();
const modes = ['custom', 'premade'].map(mode => Object.assign(element(), {dataset: {v2Mode:mode}}));
const host = Object.assign(element(), {querySelector(selector) { return {'#v2-practice-subject':subject, '#v2-practice-premade':premade, '#v2-practice-start':start, '.v2-premade-choice':choice}[selector]; }, querySelectorAll() { return modes; }});
const area = Object.assign(element(), {querySelector(selector) { return selector === '.v2-practice-area' ? host : null; }, querySelectorAll() { return []; }});
const requests = [], starts = [];
const context = {$:id => id === 'test-bank-workspace-area' ? area : null, escapeHtml:String, console,
    showTestBankInstructions:async () => true, startTestBankPremadeQuiz:async (...args) => starts.push(args),
    apiRequest:async (...args) => { requests.push(args); return {quizId:42}; }, showToast() {}, window:{}};
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/test-bank-v2.js', 'utf8'), context);
const workspace = {id:7,title:'Test Bank',accessDays:30,daysRemaining:28,subjects:[{id:1,title:'Nursing',questionCount:20}],premadeTests:[],simulationProgress:{unlocked:false}};
(async () => {
    context.renderTestBankWorkspaceV2(workspace);
    assert(!area.innerHTML.includes('data-test-bank-tab'));
    assert(!area.innerHTML.includes('test-bank-builder-form'));
    context.renderV2PracticeCard(workspace,10);
    assert(host.innerHTML.includes('SHORT & FLEXIBLE PRACTICE'));
    assert(host.innerHTML.includes('Start Warm Up Quiz'));
    await start.listeners.click();
    assert.equal(requests[0][0], '/api/test-banks/7/quizzes');
    assert.equal(requests[0][2].item_count,10);
    assert.deepEqual(Array.from(requests[0][2].subject_ids),[1]);
    assert.equal(starts[0][1],42);
    modes[1].listeners.click();
    assert(start.disabled, 'Mixed pre-made requires an available pre-made test');
    subject.value = '1'; subject.listeners.change();
    await start.listeners.click();
    assert.equal(requests[1][0],'/api/test-banks/7/subjects/1/quizzes');
    assert.equal(requests[1][2].quiz_type,'warm_up');
    context.renderV2PracticeCard(workspace,20);
    assert(host.innerHTML.includes('Start Mastery Test'));
    assert(host.innerHTML.includes('BUILD YOUR UNDERSTANDING'));
    assert(host.innerHTML.includes('Platform passing score'));
    assert(host.innerHTML.includes('Questions per test'));
    assert(host.innerHTML.includes('Each distinct passed test counts once toward simulation eligibility.'));
    console.log('V2 tab removal, warm-up card, custom and subject quiz flows passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
