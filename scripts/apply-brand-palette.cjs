// Explicit, appearance-only migration. Status greens/reds and exam-simulator colors stay intact.
const fs=require('fs'),path=require('path');
const groups={
 '#19344f': ['#041e32','#062a43','#0b3550','#073b60','#073b63','#073b65','#075176','#2f678f','#033957','#1e293b','#111827','#263746','#102a43','#052f4e','#062f50','#062e52','#245678','#052743','#07528a','#163b59','#065b6b','#032d46','#0c3550','#082b42','#29506b','#083b5c','#064b7c','#052f50','#0d5269','#08476c','#16516c','#14788b','#26365c','#112b4f','#064269','#032f48','#102b4c','#285a83','#092e47','#08283d','#0d5774','#05476b','#032b43','#0b344f','#0a2940','#0c3d56','#103e55','#0b6a82','#082b45','#0b2942','#0b3d60','#4b5563'],
 '#11a7c0': ['#08b89f','#06a990','#079d89','#058b7a','#079383','#20d3b9','#087f6d','#078b79','#08a991','#078b9b','#0e9bb3','#42bed0','#087f93','#109eaf','#1685e6','#2563eb','#3b82f6','#1681ff','#0871b9','#0799ad','#0e94aa','#2f78c4','#0aa7bf','#08a7bf','#089eac','#087a8c','#1ca7c2','#0db7d4','#05b1ee','#08b99a','#05bda2','#12bfc7','#04b7ed','#00aedd','#00a6ce','#0ba8bd','#067b89','#13b9d1','#008d7d','#067565','#087b63'],
 '#f47721': ['#ff790a','#ff850d','#ff820c','#ff820d','#ff8200','#ff8b5b','#ff9a52','#ffad73','#fb923c'],
 '#eff8fc': ['#e8eff4','#effbff','#eefaff','#f9fdff','#e4f5fa','#f7fbff','#e9f8ff','#e4edf4','#e4f6ff','#f0f4f8','#d7efff','#e9f8fb','#dff7ff','#f8fdff','#edf6ff','#e7f1fa','#eef8fb','#f7fbfd','#e8eff5','#dceeff','#e3ecf0','#e6f5f8','#eff6ff']
};
const colors=new Map(Object.entries(groups).flatMap(([target,old])=>old.map(color=>[color,target])));
for(const color of ['#090d16','#0d121d','#0d0d1a','#16161f','#20202a'])colors.set(color,'#19344f');
for(const color of ['#743cff','#7c3aed','#4f46e5','#5b21b6','#6d28d9','#c084fc','#a78bfa','#087f95','#8bded1'])colors.set(color,'#11a7c0');
for(const color of ['#eef7f6','#edfbf9','#bcefe7'])colors.set(color,'#eff8fc');
const rgb=new Map([['8,184,159','17,167,192'],['6,169,144','17,167,192'],['7,157,137','17,167,192'],['5,139,122','17,167,192'],['7,147,131','17,167,192'],['17,167,192','17,167,192'],['244,81,11','244,119,33'],['7,81,118','25,52,79'],['7,59,101','25,52,79'],['7,59,99','25,52,79'],['7,59,96','25,52,79'],['3,57,87','25,52,79'],['22,133,230','17,167,192'],['124,58,237','17,167,192'],['99,102,241','17,167,192']]);
function transform(text){
 return text.split(/(?<=\n)/).map(line=>{
  if(line.includes('#quiz-screen.prometric-simulation-exam'))return line;
  return line.replace(/#([a-f0-9]{6})([a-f0-9]{2})?\b/gi,(all,base,alpha)=>{
   const next=colors.get('#'+base.toLowerCase());return next?next+(alpha||''):all;
  }).replace(/\b(rgb(?:a)?)\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/gi,(all,fn,r,g,b)=>{
   const next=rgb.get([r,g,b].join(','));return next?fn+'('+next:all;
  });
 }).join('');
}
function files(){
 const output=fs.readdirSync('public').filter(name=>/\.(css|js|html)$/.test(name)&&!['style_v10.css','brand-palette.css'].includes(name)).map(name=>'public/'+name);
 function visit(dir){for(const entry of fs.readdirSync(dir,{withFileTypes:true})){if(entry.isSymbolicLink())continue;const name=path.join(dir,entry.name);if(entry.isDirectory())visit(name);else if(name.endsWith('.blade.php'))output.push(name);}}
 visit('resources/views');
 output.push('public/images/gra-test-prep-white.svg');
 return output;
}
function readableFills(css){
 return css.replace(/([^{}]+)\{([^{}]*)\}/g,(all,selector,body)=>{
  if(selector.includes('prometric-simulation-exam'))return all;
  const white=/(?:^|;)\s*color\s*:\s*#fff(?:fff)?\s*(?:;|$)/i.test(body);
  if(!white)return all;
  body=body.replace(/((?:^|;)\s*background(?:-color)?\s*:\s*)([^;]+)/gi,(match,prefix,value)=>prefix+value.replace(/#11a7c0\b/gi,'#087f93'));
  if(/(?:^|;)\s*background(?:-color)?\s*:[^;]*#f47721/i.test(body))
   body=body.replace(/((?:^|;)\s*color\s*:\s*)#fff(?:fff)?\b/gi,'$1#19344f');
  return selector+'{'+body+'}';
 });
}
if(require.main===module){
 for(const file of files()){
  const before=fs.readFileSync(file,'utf8');let after=transform(before);
  if(file.endsWith('.css'))after=readableFills(after);
  else if(file.endsWith('.blade.php'))after=after.replace(/<style>([\s\S]*?)<\/style>/gi,(all,css)=>'<style>'+readableFills(css)+'</style>');
  if(file.endsWith('.html') || file.endsWith('.blade.php')) {
   const versions=[['style.css?v=203','style.css?v=204'],['test-bank-v2.css?v=51','test-bank-v2.css?v=52'],['course-subject-cards.css?v=2','course-subject-cards.css?v=3'],['branding.css?v=5','branding.css?v=6'],['landing-campus.css?v=10','landing-campus.css?v=11'],['video-watermark.css?v=3','video-watermark.css?v=4'],['subject-policy.css?v=2','subject-policy.css?v=3'],['script.js?v=210','script.js?v=211'],['question-flags.js?v=6','question-flags.js?v=7'],['prometric-calculator.js?v=7','prometric-calculator.js?v=8'],["asset('branding.css') }}?v=5","asset('branding.css') }}?v=6"],["asset('style.css') }}?v=198","asset('style.css') }}?v=204"],["asset('prometric-calculator.js') }}?v=7","asset('prometric-calculator.js') }}?v=8"],["asset('admin-client-tables.js') }}?v=2","asset('admin-client-tables.js') }}?v=3"]];
   for(const [old,next] of versions)after=after.replaceAll(old,next);
   const entries=['public/index.html','resources/views/admin/layouts/app.blade.php','resources/views/admin/auth/login.blade.php','resources/views/admin/auth/password.blade.php','resources/views/admin/content/simulation-preview.blade.php'];
   if(entries.includes(file.replaceAll('\\','/')) && !after.includes('brand-palette.css')) {
    const asset=file==='public/index.html' ? 'brand-palette.css?v=1' : "{{ asset('brand-palette.css') }}?v=1";
    after=after.replace('</head>','<link rel="stylesheet" href="'+asset+'">\n</head>');
   }
   if(file.replaceAll('\\','/').includes('/admin/auth/'))after=after.replace('<body>','<body class="light-mode">');
  }
  if(before===after)continue;
  console.log(file);
  if(process.argv.includes('--write'))fs.writeFileSync(file,after);
 }
}
module.exports={transform,readableFills,colors,files};
