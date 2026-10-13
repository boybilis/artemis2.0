const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync('public/prometric-calculator.js', 'utf8');
const arithmetic = source.slice(source.indexOf('    function press(key)'), source.indexOf('    for (const key'));
const context = vm.createContext({});
vm.runInContext("let value='0', stored=null, operator=null, fresh=true; const output={textContent:'0'};" + arithmetic, context);
function calculate(keys, expected) {
    vm.runInContext(`press('C'); ${JSON.stringify(keys)}.forEach(press);`, context);
    assert.strictEqual(vm.runInContext('output.textContent', context), expected);
}
calculate(['2','+','3','='], '5');
calculate(['9','−','4','='], '5');
calculate(['6','×','7','='], '42');
calculate(['8','÷','2','='], '4');
calculate(['0','.','1','+','0','.','2','='], '0.3');
calculate(['1','÷','0','='], 'Error');
calculate(['1','÷','0','=','4'], '4');
calculate(['5','±','×','2','='], '-10');
calculate(['1','2','⌫'], '1');
calculate(['2','+','×','3','='], '6');
assert(source.includes("exam.classList.contains('prometric-simulation-exam') && !dialog.open"));
assert(source.includes('dialog.showModal()'));
console.log('Prometric calculator smoke tests passed.');
