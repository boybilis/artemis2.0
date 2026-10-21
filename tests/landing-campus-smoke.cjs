const fs=require('fs'),vm=require('vm'),assert=require('assert');
const html=fs.readFileSync('public/index.html','utf8'),source=fs.readFileSync('public/script.js','utf8');
const landing=html.slice(html.indexOf('<section id="landing-screen"'),html.indexOf('<!-- ─── Dashboard'));
const ids=[...html.matchAll(/\bid="([^"]+)"/g)].map(match=>match[1]);
assert.equal(ids.length,new Set(ids).size,'Page IDs must remain unique');
const handlers={},opened=[];
const context={$:id=>({addEventListener:(event,handler)=>{handlers[id]=handler;}}),openModal:id=>opened.push(id)};
vm.createContext(context);vm.runInContext(source.slice(source.indexOf('const triggers = ['),source.indexOf('// ───',source.indexOf('triggers.forEach'))),context);
for(const [id,modal]of [['nav-login-btn','modal-login'],['hero-login-btn','modal-login'],['nav-signup-btn','modal-signup'],['hero-signup-btn','modal-signup'],['curriculum-signup-btn','modal-signup'],['preview-signup-btn','modal-signup'],['campus-account-signup-btn','modal-signup']]){
 assert(landing.includes(`id="${id}"`));handlers[id]({});assert.equal(opened.pop(),modal);
}
assert(landing.includes('Artemis360'));
assert.equal((landing.match(/gra-test-prep-white.svg/g)||[]).length,2);
const logo=fs.readFileSync('public/images/gra-test-prep-white.svg','utf8');
assert(logo.includes('fill="#fff"'));assert(logo.includes('fill="#ff820d"'));assert(!logo.includes('<rect'));
assert(!landing.includes('href="#"'));
assert(!landing.includes('https://'),'Do not invent external destinations');
assert(landing.includes('disabled title="Main website link will be added later"'));
assert(landing.includes('Paid courses and test banks are purchased separately.'));
assert(fs.readFileSync('public/landing-campus.css','utf8').split('\n').filter(line=>line.trim()&&!line.startsWith('/*')).every(line=>line.includes('#landing-screen')));
console.log('Landing actions use existing auth flows, placeholder links stay disabled, IDs remain unique, and CSS stays scoped.');
