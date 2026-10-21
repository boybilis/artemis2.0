const fs=require('fs'),vm=require('vm'),assert=require('assert');
const lesson={id:1,subjectId:2,title:'Videos',subtopics:[{id:3,contentType:'subtopic',videoUrl:'video'}]};
const zoom={id:-9,isBatchZoom:true,contentType:'zoom_link',title:'Zoom class',zoomUrl:'old-link'};
const screens={'dashboard-screen':true,'lesson-screen':false};
const elements={};
const c={state:{user:{id:1},currentTopicIndex:null,topicProgressMap:{1:1}},currentCourseId:7,currentBatchId:8,currentSubjectId:2,
 topics:[lesson],subjects:[{id:2}],courses:[{id:7,batch_id:8}],window:{addEventListener:()=>{}},navigator:{onLine:true},
 document:{hidden:false,addEventListener:()=>{},getElementById:id=>elements[id]||{style:{display:''},classList:{contains:name=>name==='active'&&!!screens[id]}}},
 apiRequest:async(url,method,data,options)=>{assert(url.endsWith('batch_id=8'));assert(options.silent);return c.payload;},
 setInterval:fn=>{c.tick=fn;},renderCourseOverview:()=>{c.overviews++;},renderCompleteSubjectSidebar:()=>{c.outlines++;},
 renderLesson:()=>{c.reloads++;},openSubjectLearningWorkspace:()=>{c.fallbacks++;},
 getTopicFlatLearningItems:topic=>topic.subtopics.map(sub=>({sub})),overviews:0,outlines:0,reloads:0,fallbacks:0};
vm.createContext(c);vm.runInContext(fs.readFileSync('public/learner-course-zoom.js','utf8'),c);
c.payload={success:true,batchId:8,sessions:[{id:9,zoom_url:'old-link'}],workspaceTopics:[{id:1,subjectId:2,subtopics:[zoom]}]};
(async()=>{
 await c.window.refreshLearnerCourseZoom();
 assert.equal(c.courses[0].zoom_sessions[0].zoom_url,'old-link');
 assert.equal(c.topics[0],lesson);assert.equal(lesson.subtopics.length,2);
 screens['lesson-screen']=true;screens['dashboard-screen']=false;
 c.state.currentTopicIndex=0;c.window.currentFlattenedItems=[{sub:lesson.subtopics[0]}];c.window.currentLearningItemIndex=0;
 c.payload={...c.payload,sessions:[{id:9,zoom_url:'new-link'}],workspaceTopics:[{id:1,subjectId:2,subtopics:[{...zoom,zoomUrl:'new-link'}]}]};
 await c.tick();
 assert.equal(c.courses[0].zoom_sessions[0].zoom_url,'new-link');
 assert.equal(lesson.subtopics[1].zoomUrl,'new-link');assert.equal(c.reloads,0);assert.equal(c.outlines,1);
 assert.equal(c.state.topicProgressMap[1],1);assert.equal(lesson.subtopics.length,2);
 await c.tick();assert.equal(c.outlines,1);assert.equal(lesson.subtopics.length,2);
 c.window.currentFlattenedItems=[{sub:zoom}];c.window.currentLearningItemIndex=0;
 c.payload={...c.payload,sessions:[],workspaceTopics:[]};await c.tick();
 assert.equal(lesson.subtopics.length,1);assert.equal(c.fallbacks,1);
 c.apiRequest=async()=>{c.currentBatchId=10;return c.payload;};c.payload={...c.payload,sessions:[{zoom_url:'stale'}]};
 await c.tick();assert.equal(c.courses[0].zoom_sessions.length,0);
 console.log('Zoom updates refresh both views without restarting media, duplicating entries, overwriting progress, or applying stale batch responses.');
})().catch(error=>{console.error(error);process.exitCode=1;});
