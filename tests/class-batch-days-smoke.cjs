const fs=require('fs'),vm=require('vm'),assert=require('assert');
const inputs=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].map(value=>({value,checked:false}));
const context={document:{querySelectorAll:()=>inputs}};vm.createContext(context);
vm.runInContext(fs.readFileSync('public/class-batch-days.js','utf8'),context);
for(const [value,expected] of [['MWF',['Monday','Wednesday','Friday']],['TTH',['Tuesday','Thursday']],['Monday, Thursday',['Monday','Thursday']],['Tue / Sat',['Tuesday','Saturday']],['',[]]]){
 context.setClassBatchDays(value);
 assert.deepStrictEqual(inputs.filter(input=>input.checked).map(input=>input.value),expected);
}
console.log('Class-day checkboxes preselect legacy and full-name schedules without leaking previous selections.');
