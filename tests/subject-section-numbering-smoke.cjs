const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/script.js','utf8');
function element(){return {children:[],appendChild(child){this.children.push(child);},addEventListener(){}};}
const nav=element();
const topics=[
 {id:10,subjectId:1,title:'Policy',sort_order:0},
 {id:20,subjectId:2,title:'Policy',sort_order:0},
 {id:30,subjectId:3,title:'Policy',sort_order:0},
 {id:31,subjectId:3,title:'Pre-test',sort_order:17},
 {id:32,subjectId:3,title:'Lectures',sort_order:80}
];
const c={topics,state:{topicProgressMap:{}},window:{},$:()=>nav,document:{createElement:element},
 getTopicFlatLearningItems:()=>[],hasCompletedSubjectPreTest:()=>false,hasCompletedSubjectPolicy:()=>false,
 isPolicyLearningTopic:t=>t.title==='Policy',isPreTestLearningTopic:t=>t.title==='Pre-test',needsSubjectPolicyAcceptance:()=>true,escapeHtml:v=>v};
vm.createContext(c);
vm.runInContext(source.slice(source.indexOf('function renderCompleteSubjectSidebar('),source.indexOf('function outlineItemAccessible(')),c);
const numbers=()=>nav.children.map(section=>Number(section.children[0].innerHTML.match(/learning-outline-topic-number">(\d+)/)[1]));
for(const subjectId of [1,2,3]){
 nav.children=[];c.renderCompleteSubjectSidebar({subjectId,id:null});
 assert.deepEqual(numbers(),subjectId===3?[1,2,3]:[1]);
}
topics[2].sort_order=7;nav.children=[];c.renderCompleteSubjectSidebar({subjectId:3,id:null});
assert.deepEqual(numbers(),[1,2,3],'Sparse/global sort values must not be displayed as section numbers');
console.log('Section numbers start at 1 within each subject, regardless of global index or stored display-order gaps.');
