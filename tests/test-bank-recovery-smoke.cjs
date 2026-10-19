const fs=require('fs'),vm=require('vm'),assert=require('assert');
const storage=new Map(),listeners={};let requests=0;
const context={state:{user:{id:7},examType:'test_bank'},activeTestBankQuizId:9,answersList:[null],reviewQuestions:new Set([0]),qIndex:0,selected:1,quizData:[{id:1}],assessmentReviewActive:false,
 localStorage:{getItem:key=>storage.get(key)||null,setItem:(key,value)=>storage.set(key,value),removeItem:key=>storage.delete(key)},
 document:{getElementById:id=>id==='quiz-screen'?{classList:{contains:()=>true}}:null,addEventListener(){}},
 window:{addEventListener:(event,fn)=>listeners[event]=fn},setInterval(){},navigator:{onLine:true},getCsrfToken:()=>'',fetch:async()=>{requests++;return {ok:true}},console};
vm.createContext(context);vm.runInContext(fs.readFileSync('public/test-bank-recovery.js','utf8'),context);
vm.runInContext("testBankRecovery={bank:4,quiz:9,token:'safe-token',pending:true};persistTestBankDraft();",context);
assert.equal(JSON.parse([...storage.values()][0]).answers[0],1);
assert(JSON.parse([...storage.values()][0]).submitting);
context.syncTestBankDraft().then(()=>{
 assert.equal(requests,0,'Pending submission draft must not overwrite server progress');
 vm.runInContext('clearTestBankRecovery(4,9)',context);assert.equal(storage.size,0);
 console.log('Recovery draft preserves current answer and pending submission until explicit success cleanup.');
});
