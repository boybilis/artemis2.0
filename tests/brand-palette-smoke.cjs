const fs=require('fs'),assert=require('assert');
const {transform,readableFills}=require('../scripts/apply-brand-palette.cjs');
const css=fs.readFileSync('public/brand-palette.css','utf8');
for(const hex of ['#11a7c0','#f47721','#eff8fc','#19344f','#ffffff'])assert(css.includes(hex));
for(const file of ['public/index.html','resources/views/admin/layouts/app.blade.php','resources/views/admin/auth/login.blade.php','resources/views/admin/auth/password.blade.php','resources/views/admin/content/simulation-preview.blade.php'])assert(/href="[^"]*brand-palette\.css[^"]*\?v=1"/.test(fs.readFileSync(file,'utf8')),file+' must load the shared palette');
assert.equal(transform('color:#073b65;background:#08b89f;box-shadow:0 0 #073b6533;'),'color:#19344f;background:#11a7c0;box-shadow:0 0 #19344f33;');
assert.equal(transform('#10b981 #ef4444 #8ac43f #facc15'),'#10b981 #ef4444 #8ac43f #facc15','Status and exam colors must not be converted');
const protectedRule='#quiz-screen.prometric-simulation-exam .example{background:#073b65;color:#fff}';
assert.equal(transform(protectedRule),protectedRule);
assert(readableFills('.button{background:#11a7c0;color:#fff}').includes('background:#087f93'));
function luminance(hex){return hex.match(/\w\w/g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);}
function contrast(a,b){const values=[luminance(a),luminance(b)].sort((x,y)=>y-x);return (values[0]+.05)/(values[1]+.05);}
assert(contrast('087f93','ffffff')>=4.5,'White action text must remain readable on the darker aqua');
assert(contrast('19344f','eff8fc')>=4.5,'Slate text must remain readable on ice');
assert(contrast('19344f','f47721')>=4.5,'Orange actions must use dark slate text');
for(const file of ['public/style.css','public/test-bank-v2.css']){
 const styles=fs.readFileSync(file,'utf8');assert(styles.includes('--correct: #10b981; --wrong: #ef4444;'));
}
assert(fs.readFileSync('public/style.css','utf8').includes('#quiz-screen.prometric-simulation-exam .quiz-progress-bar{background:#8ac43f}'));
console.log('All page shells load the five-color palette; alpha values, semantic states, simulator colors, and key text contrasts are preserved.');
