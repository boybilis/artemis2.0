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
const topics = [1,2,3,4].map(id => Object.assign(element(),{dataset:{v2Topic:String(id)},checked:false}));
const modes = ['custom', 'premade'].map(mode => Object.assign(element(), {dataset: {v2Mode:mode}}));
const host = Object.assign(element(), {querySelector(selector) { return {'#v2-practice-subject':subject, '#v2-practice-premade':premade, '#v2-practice-start':start, '.v2-premade-choice':choice}[selector]; }, querySelectorAll(selector) { return selector === '[data-v2-mode]' ? modes : selector === '[data-v2-topic]' ? topics : []; }});
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
    assert(host.innerHTML.includes('Warm Up Quizzes'));
    assert(host.innerHTML.includes('Start Warm Up Quiz'));
    assert(host.innerHTML.includes('v2-practice-layout'));
    assert(host.innerHTML.includes('No completed results yet'));
    assert(host.innerHTML.includes('Simulation readiness'));
    assert(!area.innerHTML.includes('data-test-bank-tab'));
    assert(!area.innerHTML.includes('test-bank-builder-form'));
    assert(area.innerHTML.includes('Select Simulation'));
    context.renderV2SimulationCard({...workspace, simulationProgress:{warmUpPassed:7,masteryPassed:3,unlocked:false}});
    assert(host.innerHTML.includes('7/10 passed'));
    assert(host.innerHTML.includes('3/5 passed'));
    assert(host.innerHTML.includes('Pass 3 more distinct Warm Up Quizzes and 2 more distinct Mastery Tests to unlock.'));
    assert.equal((host.innerHTML.match(/>Locked<\/button>/g) || []).length,2);
    context.renderV2SimulationCard({...workspace, simulationProgress:{warmUpPassed:10,masteryPassed:5,unlocked:true},premadeTests:[{id:9,isSimulation:true,locked:false}]});
    assert(host.innerHTML.includes('Start Simulation'));
    assert(host.innerHTML.includes('Not available'));
    context.renderV2PracticeCard(workspace,10);
    assert(host.innerHTML.includes('SHORT & FLEXIBLE PRACTICE'));
    assert(host.innerHTML.includes('Start Warm Up Quiz'));
    assert(start.disabled, 'A topic must be selected');
    topics[0].checked = true; topics[0].listeners.change();
    await start.listeners.click();
    assert.equal(requests[0][0], '/api/test-banks/7/quizzes');
    assert.equal(requests[0][2].item_count,10);
    assert.deepEqual(Array.from(requests[0][2].subject_ids),[1]);
    assert.equal(starts[0][1],42);
    modes[1].listeners.click();
    await start.listeners.click();
    assert.equal(requests[1][0],'/api/test-banks/7/subjects/1/quizzes');
    assert.equal(requests[1][2].quiz_type,'warm_up');
    context.renderV2PracticeCard({...workspace,subjects:[1,2,3,4].map(id => ({id,title:`Topic ${id}`,questionCount:20}))},10);
    topics[1].checked = true; topics[1].listeners.change();
    assert.equal(subject.textContent,'Selected topics');
    topics[2].checked = true; topics[2].listeners.change();
    assert.equal(subject.textContent,'Selected topics');
    topics[3].checked = true; topics[3].listeners.change();
    assert.equal(subject.textContent,'Mixed');
    await start.listeners.click();
    assert.deepEqual(Array.from(requests[2][2].subject_ids),[1,2,3,4]);
    context.renderV2PracticeCard(workspace,20);
    assert(host.innerHTML.includes('Start Mastery Test'));
    assert(host.innerHTML.includes('BUILD YOUR UNDERSTANDING'));
    assert(host.innerHTML.includes('Platform passing score'));
    assert(host.innerHTML.includes('Questions per test'));
    assert(host.innerHTML.includes('Each distinct passed test counts once toward simulation eligibility.'));
    context.renderV2PracticeCard({...workspace,history:[{id:2,quizId:8,title:'Nursing Test',coverage:'Nursing',totalItems:20,total:20,score:10,passed:false,takenAt:'2026-10-12'}]},20);
    assert(host.innerHTML.includes('50%'));
    assert(host.innerHTML.includes('Failed · Try again'));
    assert(host.innerHTML.includes('data-v2-review'));
    assert(host.innerHTML.includes('data-v2-retake'));
    const css = fs.readFileSync('public/test-bank-v2.css','utf8');
    assert(css.includes('grid-template-columns:minmax(0,3fr) minmax(0,1fr)'));
    assert(css.includes('@media(max-width:900px)'));
    console.log('V2 tab removal, warm-up card, custom and subject quiz flows passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
