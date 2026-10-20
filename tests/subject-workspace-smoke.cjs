const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/script.js','utf8'),page=fs.readFileSync('public/index.html','utf8');
for(const percentage of [0,50,100]){
 const elements={},calls=[];
 const context={subjects:[{id:16,title:'Renal Nursing'}],currentSubjectId:null,state:{currentTopicIndex:0},window:{},
  $:id=>elements[id] ||= {style:{},textContent:''},pauseActiveLessonVideo:()=>calls.push('pause'),setLessonSidebarOpen:()=>{},
  getSubjectLearningProgress:()=>({completed:percentage/10,total:10,percentage}),
  renderCompleteSubjectSidebar:topic=>calls.push(topic),showScreen:id=>calls.push(id)};
 vm.createContext(context);
 vm.runInContext(source.slice(source.indexOf('function openSubjectLearningWorkspace('),source.indexOf('function renderCourseOverview(')),context);
 context.openSubjectLearningWorkspace(16);
 assert.equal(context.currentSubjectId,16);assert.equal(context.state.currentTopicIndex,null);
 assert.equal(calls[1].subjectId,16);assert.equal(calls[1].id,null);
 assert.equal(calls[2],'lesson-screen');assert.equal(elements['subject-workspace-welcome'].style.display,'flex');
 assert(elements['subject-workspace-progress'].textContent.includes(`${percentage}%`));
 assert.equal(elements['subtopic-header-title'].textContent,'Renal Nursing');
 assert.equal(elements['no-subtopics-msg'].style.display,'none');
}
const cards=source.slice(source.indexOf('function renderSubjects('),source.indexOf('function setupSubjectCarousel('));
assert(cards.includes('openSubjectLearningWorkspace(Number(subject.id))'));
assert(!cards.includes('openTopic(destination.originalIndex)'));
assert(page.includes('id="subject-workspace-welcome"'));
console.log('Subject Open always shows the full workspace at 0%, in progress, and 100%, without selecting an empty topic.');
