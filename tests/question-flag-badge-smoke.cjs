const fs=require('fs'),vm=require('vm'),assert=require('assert');
const sources=[fs.readFileSync('public/question-flags.js','utf8'),fs.readFileSync('resources/views/admin/layouts/app.blade.php','utf8')];
for(const source of sources){
 const styles={};const badge={style:{setProperty:(key,value,priority)=>{styles[key]={value,priority};}},setAttribute(){}};
 const context={badge};vm.createContext(context);
 const start=source.includes('function paintUnread(count)')?source.indexOf('function paintUnread(count)'):source.indexOf('const paintUnread=count=>');
 vm.runInContext(source.slice(start,source.indexOf('paintUnread(0);',start))+'\npaintUnread(2);',context);
 assert.equal(badge.hidden,false);assert.equal(badge.textContent,'2');assert.equal(styles.display.value,'inline-flex');
 vm.runInContext('paintUnread(0)',context);
 assert.equal(badge.hidden,true);assert.equal(badge.textContent,'');assert.equal(styles.display.value,'none');assert.equal(styles.display.priority,'important');
 assert(source.includes('revision===unreadRevision'));
 assert(source.includes('paintUnread(result.unreadCount)'));
}
console.log('Learner and management badges disappear at zero and ignore stale unread refreshes.');
