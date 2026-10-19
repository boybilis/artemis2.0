const fs=require('fs'),vm=require('vm'),assert=require('assert');
const calls=[];
const context={window:{addEventListener(){}},state:{user:{role:'student'}},navigator:{onLine:true},setInterval(){},
 persistTestBankDraft:()=>calls.push('preserve'),clearInterval:()=>calls.push('stop'),quizTimerInterval:1,testBankExamActive:true,activeTestBankQuizId:9,testBankRecovery:{},testBankExitWarningOpen:false,
 document:{querySelectorAll:()=>[]},exitTestBankFullscreen:async()=>calls.push('exit'),resetLearnerSessionState:()=>calls.push('reset'),showScreen:id=>calls.push(id),showToast:()=>{}};
vm.createContext(context);vm.runInContext(fs.readFileSync('public/learner-session.js','utf8'),context);
context.window.handleLearnerSessionReplaced();
assert.deepEqual(calls,['preserve','stop','exit','reset','landing-screen']);
assert.equal(context.state.user,null);assert.equal(context.activeTestBankQuizId,null);assert.equal(context.testBankExamActive,false);
console.log('Replaced session saves the draft and exits without cancelling the attempt.');
