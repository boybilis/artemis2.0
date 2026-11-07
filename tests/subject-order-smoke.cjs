const fs=require('fs'),vm=require('vm'),assert=require('assert');
const handles=[{},{}],edits=[{dataset:{}},{dataset:{}}];
const a={dataset:{subjectId:'1'},querySelector:()=>edits[0]},b={dataset:{subjectId:'2'},querySelector:()=>edits[1]};
let rows=[a,b],config,reply,calls=0,disabled;
const list={dataset:{reorderUrl:'/reorder',csrf:'token'},querySelectorAll:s=>s==='tr[data-subject-id]'?rows:handles,setAttribute(){},removeAttribute(){},addEventListener(){},appendChild(row){rows=rows.filter(r=>r!==row);rows.push(row);}};
const notices=[];
const c={document:{getElementById:()=>list},Sortable:class{constructor(el,opts){config=opts;}option(k,v){disabled=v;}},showAdminToast:(...args)=>notices.push(args),fetch:async(url,options)=>{calls++;assert.equal(options.headers['X-CSRF-TOKEN'],'token');assert.deepEqual(JSON.parse(options.body).ordered_ids,[2,1]);return new Promise(resolve=>{reply=resolve;});}};
vm.createContext(c);vm.runInContext(fs.readFileSync('public/subject-order.js','utf8'),c);
(async()=>{
 assert.equal(config.handle,'.subject-drag-handle');assert.equal(config.direction,'vertical');
 config.onStart();await config.onEnd();assert.equal(calls,0);
 config.onStart();rows=[b,a];let saving=config.onEnd();assert(disabled);assert(handles[0].disabled);
 reply({ok:true,json:async()=>({success:true,message:'Saved'})});await saving;
 assert.equal(edits[1].dataset.order,'1');assert.equal(edits[0].dataset.order,'2');assert(!disabled);assert.equal(notices.at(-1)[1],'success');
 rows=[a,b];config.onStart();rows=[b,a];saving=config.onEnd();reply({ok:false,json:async()=>({message:'Failed'})});await saving;
 assert.deepEqual(rows,[a,b]);assert(!handles[0].disabled);assert.equal(notices.at(-1)[1],'error');
 console.log('Subject drag order saves via AJAX, updates edit order, skips unchanged drops, and rolls back failures.');
})().catch(error=>{console.error(error);process.exitCode=1;});
