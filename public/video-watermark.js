(() => {
    const wrap=document.getElementById('video-iframe-wrap');
    const button=document.getElementById('video-fullscreen-btn');
    const video=document.getElementById('uploaded-video-player');
    const frame=document.getElementById('video-player');
    const label=document.getElementById('video-watermark-label');
    if(!wrap || !button)return;
    function updateWatermark(){
        const user=typeof state==='undefined' ? null : state.user;
        const name=user ? String(user.name || [user.first_name,user.last_name].filter(Boolean).join(' ')).trim() : '';
        if(label)label.textContent=name;
    }
    window.updateLearnerVideoWatermark=updateWatermark;
    updateWatermark();
    const fullscreenElement=()=>document.fullscreenElement || document.webkitFullscreenElement;
    const expanded=()=>fullscreenElement()===wrap || wrap.classList.contains('is-expanded');
    function updateButton(){
        button.setAttribute('aria-label',expanded()?'Exit video full screen':'View watermarked video full screen');
        button.setAttribute('aria-pressed',String(expanded()));
        button.title=expanded()?'Exit full screen':'View full screen';
    }
    function closeFallback(){wrap.classList.remove('is-expanded');document.body.classList.remove('gra-video-expanded');updateButton();}
    async function toggleFullscreen(){
        updateWatermark();
        if(wrap.classList.contains('is-expanded')){closeFallback();return;}
        if(fullscreenElement()===wrap){
            const exit=document.exitFullscreen || document.webkitExitFullscreen;
            if(exit)await exit.call(document);
        }else{
            const request=wrap.requestFullscreen || wrap.webkitRequestFullscreen;
            try{
                if(!request)throw new Error('Use expanded player');
                await request.call(wrap);
            }catch(_){
                wrap.classList.add('is-expanded');document.body.classList.add('gra-video-expanded');
            }
        }
        updateButton();
    }
    if(video){
        video.controlsList?.add('nofullscreen');
        video.disablePictureInPicture=true;
        video.addEventListener('dblclick',event=>{event.preventDefault();toggleFullscreen();});
    }
    if(frame){frame.removeAttribute('allowfullscreen');frame.allowFullscreen=false;}
    button.addEventListener('click',toggleFullscreen);
    document.addEventListener('fullscreenchange',updateButton);
    document.addEventListener('webkitfullscreenchange',updateButton);
    document.addEventListener('keydown',event=>{if(event.key==='Escape' && wrap.classList.contains('is-expanded'))closeFallback();});
    const screen=document.getElementById('lesson-screen');
    if(screen)new MutationObserver(()=>{
        updateWatermark();
        if(screen.classList.contains('active'))return;
        closeFallback();
        if(fullscreenElement()===wrap){const exit=document.exitFullscreen || document.webkitExitFullscreen;Promise.resolve(exit?.call(document)).catch(()=>{});}
    }).observe(screen,{attributes:true,attributeFilter:['class']});
    updateButton();
})();
