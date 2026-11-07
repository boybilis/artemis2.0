const fs=require('fs'),vm=require('vm'),assert=require('assert');
const css=fs.readFileSync('public/subject-policy.css','utf8');
assert(css.includes('position:fixed;inset:0;margin:auto;height:fit-content'), 'Dialog must override the global margin reset and center within the viewport');
function element(){return {events:{},addEventListener(name,handler){this.events[name]=handler;}};}
const dialog=element(),check=element(),accept=element(),decline=element();
dialog.showModal=()=>{dialog.open=true;};dialog.close=()=>{dialog.open=false;};
const elements={'subject-policy-dialog':dialog,'subject-policy-agree-check':check,'subject-policy-accept':accept,'subject-policy-decline':decline,'lesson-screen':{classList:{contains:()=>true}}};
const notices=[],views=[];let resolveSave,requests=0,reads=false;
const topic={id:1,subjectId:16,title:'Policy'};
const c={window:{},document:{getElementById:id=>elements[id]},state:{user:{id:8},currentTopicIndex:0},currentCourseId:2,currentBatchId:3,currentSubjectId:16,
 subjects:[{id:16,policyAcceptanceRequired:true,policyAccepted:false}],topics:[topic],
 needsSubjectPolicyAcceptance:()=>!c.subjects[0].policyAccepted,isPolicyLearningTopic:t=>t.title==='Policy',hasReadSubjectPolicy:()=>reads,
 MutationObserver:class{observe(){}},pauseActiveLessonVideo(){},renderSubjects:()=>views.push('cards'),showScreen:screen=>views.push(screen),renderCompleteSubjectSidebar(){},showToast:(...args)=>notices.push(args),
 apiRequest:async(url,method,data)=>{requests++;assert.equal(method,'POST');assert(data.agreed);assert.equal(data.batch_id,3);return await new Promise(resolve=>resolveSave=resolve);}};
vm.createContext(c);vm.runInContext(fs.readFileSync('public/subject-policy.js','utf8'),c);
(async()=>{
 assert(!c.window.maybePromptSubjectPolicy(topic));reads=true;
 assert(c.window.maybePromptSubjectPolicy(topic));assert(dialog.open);assert(accept.disabled);
 await accept.events.click();assert.equal(requests,0);
 decline.events.click();assert(!dialog.open);assert.equal(requests,0);assert.deepEqual(views,['cards','dashboard-screen']);assert(!c.subjects[0].policyAccepted);
 c.currentSubjectId=16;c.window.maybePromptSubjectPolicy(topic);check.checked=true;check.events.change();assert(!accept.disabled);
 const saving=accept.events.click();assert(accept.disabled);assert(check.disabled);assert(decline.disabled);
 await accept.events.click();assert.equal(requests,1);
 resolveSave({success:true,policyAccepted:true,message:'Accepted'});await saving;
 assert(c.subjects[0].policyAccepted);assert(!dialog.open);assert.equal(notices.at(-1)[1],'success');assert(!c.window.maybePromptSubjectPolicy(topic));
 c.subjects[0].policyAccepted=false;c.window.maybePromptSubjectPolicy(topic);check.checked=true;check.events.change();
 c.apiRequest=async()=>{throw Error('Offline');};await accept.events.click();assert(dialog.open);assert(!c.subjects[0].policyAccepted);assert(!accept.disabled);assert.equal(notices.at(-1)[1],'error');
 let cancelled=false;dialog.events.cancel({preventDefault(){cancelled=true;}});assert(cancelled);assert(!dialog.open);
 console.log('Policy consent requires completion and checkbox; decline returns to cards, failed saves stay locked, and saved consent is not prompted again.');
})().catch(error=>{console.error(error);process.exitCode=1;});
