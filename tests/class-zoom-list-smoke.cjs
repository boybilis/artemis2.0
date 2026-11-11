const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const assert=require('node:assert/strict');

const view=readFileSync('resources/views/admin/classes/index.blade.php','utf8');
const render=view.slice(view.indexOf('function renderClassZoomList()'),view.indexOf("zoomField('zoomListSearch').addEventListener"));
const fields={'zoomListSearch':{value:''},class_zoom_list:{},zoomListPage:{},zoomListPrev:{},zoomListNext:{}};
const context={
    zoomField:id=>fields[id],zoomPage:1,escapeClass:String,formatClassZoomSchedule:()=> 'Monday',
    classZoomSessions:Array.from({length:12},(_,i)=>({id:i+1,title:`Meeting ${i+1}`,status:i===11?'cancelled':'scheduled'})),
};
const draw=()=>runInNewContext(render+';renderClassZoomList()',context);
draw();
assert.equal(fields.class_zoom_list.innerHTML.match(/<tr>/g).length,10);
assert.equal(fields.zoomListPage.textContent,'1–10 of 12');
assert.equal(fields.zoomListPrev.disabled,true);
context.zoomPage=2;
draw();
assert.equal(fields.zoomListPage.textContent,'11–12 of 12');
assert.equal(fields.zoomListNext.disabled,true);
fields.zoomListSearch.value='CANCELLED';
draw();
assert.equal(fields.zoomListPage.textContent,'1–1 of 1');
assert.match(fields.class_zoom_list.innerHTML,/>Meeting 12</);
fields.zoomListSearch.value='missing';
draw();
assert.match(fields.class_zoom_list.innerHTML,/No matching meetings/);
console.log('Saved meeting search and 10-row pagination passed.');
