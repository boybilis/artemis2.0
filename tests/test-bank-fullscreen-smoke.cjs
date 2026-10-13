const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync('public/script.js', 'utf8');
const calls = [];
const document = {
    fullscreenElement: null,
    documentElement: {async requestFullscreen() { calls.push('enter'); document.fullscreenElement = this; }},
    async exitFullscreen() { calls.push('exit'); document.fullscreenElement = null; },
    body: {classList: {remove(value) { calls.push(value); }}},
};
const context = {document};
vm.createContext(context);
vm.runInContext('let testBankExamActive = true;\n' + source.slice(source.indexOf('function currentTestBankFullscreenElement()'), source.indexOf('async function invalidateActiveTestBankExam(')), context);
(async () => {
    await context.enterTestBankFullscreen();
    assert.strictEqual(document.fullscreenElement, document.documentElement);
    await context.enterTestBankFullscreen();
    assert.strictEqual(calls.filter(value => value === 'enter').length, 1);
    await context.exitTestBankFullscreen();
    assert.strictEqual(document.fullscreenElement, null);
    assert.strictEqual(vm.runInContext('testBankExamActive', context), false);
    document.documentElement.requestFullscreen = null;
    await assert.rejects(context.enterTestBankFullscreen(), /Fullscreen unavailable/);
    const instructions = source.slice(source.indexOf('function showTestBankInstructions('), source.indexOf('async function loadTopicsIfNeeded('));
    assert(instructions.includes('await enterTestBankFullscreen();\n                finish(true)'));
    assert(source.includes("document.addEventListener('fullscreenchange', handleTestBankFullscreenChange)"));
    console.log('Fullscreen entry, exit, unsupported browsers, and instruction confirmation checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
