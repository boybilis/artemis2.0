const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/video-watermark.js','utf8');
function element(){const classes=new Set();return {events:{},attrs:{},classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c)},addEventListener(name,handler){this.events[name]=handler;},setAttribute(name,value){this.attrs[name]=value;},removeAttribute(name){delete this.attrs[name];}};}
async function run(native){
 const wrap=element(),button=element(),video=element(),frame=element(),body=element(),screen=element();screen.classList.add('active');
 const removed=[];video.controlsList={add:value=>removed.push(value)};
 const elements={'video-iframe-wrap':wrap,'video-fullscreen-btn':button,'uploaded-video-player':video,'video-player':frame,'lesson-screen':screen};
 const document={events:{},body,getElementById:id=>elements[id],addEventListener(name,handler){this.events[name]=handler;}};
 const c={document,MutationObserver:class{constructor(handler){c.screenChanged=handler;}observe(){}}};
 if(native){wrap.requestFullscreen=async()=>{document.fullscreenElement=wrap;};document.exitFullscreen=async()=>{document.fullscreenElement=null;};}
 vm.createContext(c);vm.runInContext(source,c);
 assert(removed.includes('nofullscreen'));assert(video.disablePictureInPicture);assert.equal(frame.allowFullscreen,false);
 await button.events.click();
 assert.equal(button.attrs['aria-pressed'],'true');
 if(native){assert.equal(document.fullscreenElement,wrap,'Fullscreen must include watermark, not just video');await button.events.click();assert.equal(document.fullscreenElement,null);}
 else{assert(wrap.classList.contains('is-expanded'));document.events.keydown({key:'Escape'});assert(!wrap.classList.contains('is-expanded'));assert(!body.classList.contains('gra-video-expanded'));}
 await button.events.click();screen.classList.remove('active');c.screenChanged();await Promise.resolve();
 assert(!wrap.classList.contains('is-expanded'));assert(!body.classList.contains('gra-video-expanded'));assert(!document.fullscreenElement);
}
(async()=>{
 await run(true);await run(false);
 const html=fs.readFileSync('public/index.html','utf8');
 const viewer=html.slice(html.indexOf('id="video-iframe-wrap"'),html.indexOf('<!-- Video unavailable -->'));
 assert(viewer.includes('class="gra-video-watermark"'));assert(viewer.includes('GRA Test Prep'));
 assert(viewer.includes('id="video-player"'));assert(viewer.includes('id="uploaded-video-player"'));
 assert(viewer.includes("fullscreen 'none'"));
 const css=fs.readFileSync('public/video-watermark.css','utf8');assert(css.includes('pointer-events:none'));assert(css.includes('prefers-reduced-motion:reduce'));
 console.log('Watermark covers both player types; fullscreen keeps the wrapper, fallback exits safely, and playback controls remain available.');
})().catch(error=>{console.error(error);process.exitCode=1;});
