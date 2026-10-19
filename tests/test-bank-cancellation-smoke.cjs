const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/script.js','utf8');
const calls=[];
let fail=true;
const context={activeTestBankId:4,activeTestBankQuizId:9,testBankAttemptInvalidating:false,testBankExamActive:true,quizTimerInterval:1,
 apiRequest:async()=>{if(fail)throw new Error('Offline');return {success:true};},clearInterval:()=>calls.push('timer'),clearTestBankRecovery:()=>calls.push('clear'),
 exitTestBankFullscreen:async()=>calls.push('exit'),showToast:()=>{},showScreen:()=>calls.push('navigate'),openTestBankWorkspace:()=>{},showTestBankExitWarning:()=>calls.push('warning'),currentTestBankFullscreenElement:()=>true};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('async function invalidateActiveTestBankExam('),source.indexOf('function showTestBankExitWarning(')),context);
(async()=>{
 await context.invalidateActiveTestBankExam();
 assert.deepEqual(calls,['warning']);assert.equal(context.activeTestBankQuizId,9);assert.equal(context.testBankExamActive,true);
 fail=false;await context.invalidateActiveTestBankExam();
 assert.deepEqual(calls,['warning','timer','clear','exit','navigate']);assert.equal(context.activeTestBankQuizId,null);
 console.log('Cancellation preserves progress on failure and clears only after server confirmation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
