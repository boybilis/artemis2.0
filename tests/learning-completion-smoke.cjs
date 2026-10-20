const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/script.js','utf8');
let resolveSave,sidebarUpdates=0;
const button={disabled:false,dataset:{readyTitle:'Continue'},title:''};
const topic={id:1,subjectId:16,title:'End User Agreement'};
const context={topics:[topic],state:{currentTopicIndex:0,topicProgressMap:{}},window:{currentLearningItemIndex:0,currentUnlockedIdx:0,currentFlattenedItems:[{sub:{id:3},subIndex:0,type:'doc'}]},
 $:()=>button,document:{querySelector:()=>null},apiRequest:()=>new Promise(resolve=>resolveSave=resolve),renderCompleteSubjectSidebar:()=>sidebarUpdates++,showToast(){},console};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('async function markCurrentLearningItemComplete('),source.indexOf('function renderSubtopicAssessment(')),context);
(async()=>{
 const saving=context.markCurrentLearningItemComplete(0);
 assert.equal(button.disabled,true,'Continue must stay disabled until progress is saved');
 assert.equal(sidebarUpdates,0);
 resolveSave({max_unlocked_index:1});assert.equal(await saving,true);
 assert.equal(button.disabled,false);assert.equal(sidebarUpdates,1);
 assert.equal(context.state.topicProgressMap[1],1);
 context.window.currentUnlockedIdx=0;
 context.apiRequest=async()=>{throw new Error('Offline');};
 assert.equal(await context.markCurrentLearningItemComplete(0),false);
 assert.equal(button.disabled,true);assert.equal(sidebarUpdates,1);
 console.log('Continue unlocks and refreshes the subject outline only after successful completion save.');
})().catch(error=>{console.error(error);process.exitCode=1;});
